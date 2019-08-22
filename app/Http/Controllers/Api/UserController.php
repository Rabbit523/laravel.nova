<?php

namespace App\Http\Controllers\Api;

use App\Services\AWS\AwsS3Service;
use App\User;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\UpdateUser;
use App\Http\Requests\Api\UpdateUserCompany;
use App\Http\Requests\Api\UpdateContactSettings;
use App\Http\Transformers\UserTransformer;

use Illuminate\Support\Facades\Lang;

use Illuminate\Http\Request;
use Newsletter;

use App\Notifications\TrialStarted as TrialStartedNotification;
use App\Notifications\UsernameChanged as UsernameChangedNotification;

use DrewM\MailChimp\Webhook;

class UserController extends ApiController
{
    /**
     * UserController constructor.
     *
     * @param UserTransformer $transformer
     */
    public function __construct(UserTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Get the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        return $this->respondWithTransformer(context());
    }

    /**
     * Download user branding image from aws bucket
     *
     * @param User $user
     * @param $image_type
     * @param AwsS3Service $awsS3Service
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response|mixed
     */
    public function showBrandingImage(User $user, $image_type, AwsS3Service $awsS3Service)
    {
        if ($image_type != 'logo' && $image_type != 'icon') {
            return $this->respondNotFound();
        }

        if (!array_has($user->settings, $image_type . '_path')) {
            return $this->respondNotFound();
        }

        try {
            return $awsS3Service
                ->setUser($user)
                ->setBucketName()
                ->downloadImageFromUserBucket(
                    array_get($user->settings, $image_type . '_path')
                );
        } catch (AwsException | FileNotFoundException $e) {
            return response('Image not found', 404);
        } catch (\Exception $e) {
            log_error($e);
            return response('Internal server error', 500);
        }
    }

    public function switch(Request $request)
    {
        // TODO: check have access to that context
        $user = user();
        //$team = find by $request->input('context_id')
        $user->context_id = $request->input('context_id');
        $user->save();
        return $this->respondWithTransformer(context());
    }

    /**
     * Update the authenticated user and return the user if successful.
     *
     * @param UpdateUser $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateUser $request)
    {
        $user = user();
        $on_mail_list = $user->on_mail_list;
        $name = $user->name;
        $data = $request->input('user');
        $user->update(array_only($data, ['name', 'on_mail_list', 'language']));

        if (\App::environment('prod')) {
            if ($on_mail_list != $user->on_mail_list) {
                if ($user->on_mail_list) {
                    Newsletter::subscribe($user->email, ['NAME' => $user->name]);
                } else {
                    Newsletter::unsubscribe($user->email);
                }
            }
        }
        if ($name != $user->name) {
            // TODO: for each user team notify about username change
            // $team->notify(new UsernameChangedNotification($user, $name));
        }
        return $this->respondWithTransformer($user);
    }

    /**
     * Delete user account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $token = $request->user()->token();
        $token->revoke();

        try {
            user()->delete();
        } catch (\Exception $e) {
            logger()->error($e->getMessage());
        }
        // TODO: listen on event and delete everything
        try {
            user()
                ->projects()
                ->delete();
            user()
                ->contacts()
                ->delete();
            user()
                ->products()
                ->delete();
        } catch (\Exception $e) {
            logger()->error($e->getMessage());
        }

        return $this->respondSuccess();
    }

    /**
     * Get the authenticated user company information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCompany()
    {
        $company = user()->company;
        if ($company) {
            $meta = $company->meta ?? [];
            $company = array_merge($meta, $company->toArray());
            $company['industry'] = user()->company->industry;
        }
        return $this->respond(compact('company'));
    }

    /**
     * Update the authenticated user company information.
     *
     * @param UpdateUserCompany $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCompany(UpdateUserCompany $request)
    {
        if (!$request->has('company')) {
            return $this->respondError("no company data present", 400);
        }
        $data = $request->get('company');
        unset($data['industry']);
        $data['meta'] = $data;
        $company = user()
            ->company()
            ->updateOrCreate(['id' => $request->input('company.id')], $data);
        if (is_null(user()->sender_address)) {
            user()->update(['sender_address' => str_random(10)]);
        }
        return $this->respond(compact('company'));
    }

    /**
     * Update the authenticated user and return the user if successful.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addCard()
    {
        $user = user();
        $token = request()->input('card.token');
        if (!$user->stripe_id) {
            $user->createAsStripeCustomer();
        }
        $user->updateCard($token);
        return $this->respondSuccess();
    }

    /**
     * Start trial for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function startTrial()
    {
        $user = user();
        if ($user->onGenericTrial()) {
            return $this->respondError(Lang::getFromJson("already on trial"), 400);
        } elseif ($user->trial_ends_at && $user->trial_ends_at->isPast()) {
            return $this->respondError(Lang::getFromJson("trial already expired"), 400);
        }
        $user->trial_ends_at = now()->addDays(30);
        $user->save();
        $user->notify(new TrialStartedNotification());
        return $this->respondSuccess();
    }

    public function mailchimpWebhook()
    {
        Webhook::subscribe('unsubscribe', function ($data) {
            $user = User::where('email', $data['email'])->first();
            if ($user) {
                $user->on_mail_list = false;
                $user->save();
            }
        });

        Webhook::subscribe('subscribe', function ($data) {
            $user = User::where('email', $data['email'])->first();
            if ($user) {
                $user->on_mail_list = true;
                $user->save();
            }
        });

        return "ok";
    }

    /**
     * @param UpdateContactSettings $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSettings(UpdateContactSettings $request, AwsS3Service $awsS3Service)
    {
        $settings = $request->get('settings');

        try {
            // update remote first
            if (
                array_has($settings, 'logo') ||
                array_get(user()->settings, 'primary_color') !=
                array_get($settings, 'primary_color')
            ) {
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                \Stripe\Account::update(user()->connect_id, [
                    "settings" => [
                        'branding' => array_only($settings, ['primary_color', 'logo']),
                    ],
                ]);
            }

            if (request()->has('settings.icon_file')) {
                $settings['icon_path'] = $awsS3Service->putFileToUserBucket(
                    'branding',
                    $request->file('settings.icon_file')
                );
            }

            if (request()->has('settings.logo_file')) {
                $settings['logo_path'] = $awsS3Service->putFileToUserBucket(
                    'branding',
                    $request->file('settings.logo_file')
                );
            }

            if (request()->has('settings.strip_file')) {
                $settings['strip_path'] = $awsS3Service->putFileToUserBucket(
                    'branding',
                    $request->file('settings.strip_file')
                );
            }
            $data = [];
            if (array_has($settings, 'sender_address')) {
                $data['sender_address'] = array_get($settings, 'sender_address');
            }
            if (empty(user()->sender_address) && empty($data['sender_address'])) {
                $data['sender_address'] = str_random(10);
            }
            $data['settings'] = array_merge(
                (array)user()->settings,
                array_only($settings, [
                    'primary_color',
                    'secondary_color',
                    'hide_kinchaku_branding',
                    'icon_path',
                    'logo_path',
                    'strip_path',
                    'brand_name',
                ])
            );
            user()->update($data);
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            return response()->json(["error" => ["message" => $e->getMessage()]], 400);
        } catch (\Exception $e) {
            log_error($e);
            return response()->json(
                ["error" => ["message" => Lang::getFromJson('Error saving settings.')]],
                500
            );
        }
        return ok();
    }
}
