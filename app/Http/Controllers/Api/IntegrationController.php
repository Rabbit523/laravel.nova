<?php

namespace App\Http\Controllers\Api;

use App\Integration;
use App\Http\Controllers\ApiController;
use App\Http\Transformers\IntegrationTransformer;
use App\Jobs\ImportGoogleContacts;
use App\Jobs\ImportHubSpotContacts;
use App\Jobs\ImportMailchimpContacts;
use App\Jobs\ImportFreee;
use Google_Service_People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Cache;
use Socialite;

class IntegrationController extends ApiController
{
    /**
     * IntegrationController constructor.
     *
     * @param IntegrationTransformer $transformer
     */
    public function __construct(IntegrationTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Get all integrations.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $integrations = user()
                ->integrations()
                ->get();
        } catch (\Exception $e) {
            logger()->error($e->getMessage());
            return $this->respond(['integrations' => []]);
        }
        return $this->respondWithTransformer($integrations);
    }

    public function createWebhook(Request $request)
    {
        $data = $request->get('webhook');
        $data['service'] = 'webhook';
        $data['remote_id'] = $data['url'];
        $data['details'] = ['enabled' => true];
        unset($data['enabled']);
        unset($data['url']);
        user()
            ->integrations()
            ->create($data);

        return $this->respondSuccess();
    }

    public function generateApikey(Request $request)
    {
        $key = make_UUID();
        $api = user()
            ->integrations()
            ->where('service', 'api')
            ->first();
        if ($api) {
            $api->remote_id = $key;
            $api->save();
        } else {
            $data = [];
            $data['service'] = 'api';
            $data['remote_id'] = $key;
            $data['details'] = [];
            user()
                ->integrations()
                ->create($data);
        }

        return $this->respondSuccess();
    }

    public function updateWebhook(Request $request)
    {
        if (!$request->has('webhook')) {
            return $this->respondError("no webhook data present", 400);
        }
        $data = $request->get('webhook');
        $data['remote_id'] = $data['url'];
        unset($data['enabled']);
        $webhook = user()
            ->integrations()
            ->where('service', 'webhook')
            ->first();

        $webhook->update($data);
        return $this->respondSuccess();
    }

    public function testWebhook()
    { }

    public function disableWebhook(Request $request)
    {
        $webhook = user()
            ->integrations()
            ->where('service', 'webhook')
            ->first();
        $details = $webhook->details;
        $details['enabled'] = !$request->input('disable');
        $webhook->details = $details;
        $webhook->save();
        return $this->respondSuccess();
    }

    public function delete(Integration $integration)
    {
        if ($integration->user_id != auth()->id() && user()->acl < 9) {
            // && !$customer->members->contains(auth()->id()) // TODO: check team customer access
            return $this->respondForbidden();
        }
        Cache::forget('integration.' . $integration->remote_id);
        $integration->delete();
        return $this->respondSuccess();
    }

    public function saveStripe(Request $request)
    {
        $secret = $request->input('stripe.secret');
        $project_id = $request->input('stripe.project');
        if (!$project_id) {
            return $this->respondError("missing project", 400);
        }
        $result = false;

        $api = user()
            ->integrations()
            ->where('service', 'stripe')
            ->first();

        if ($api) {
            $api->details = ['secret' => $secret, 'project' => $project_id];
            Cache::forget('integration.' . $api->remote_id);
            $result = $api->save() ? true : false;
        } else {
            $api = user()
                ->integrations()
                ->create([
                    'service' => 'stripe',
                    'remote_id' => make_UUID(),
                    'details' => ['secret' => $secret, 'project' => $project_id]
                ]);

            $result = $api ? true : false;
        }

        if ($result) {
            $this->respondSuccess();
        }

        $this->respondInternalError();
    }

    public function createMailchimp(Request $request)
    {
        user()
            ->integrations()
            ->create([
                'service' => 'mailchimp',
                'remote_id' => $request->input('mailchimp.key'),
                'details' => []
            ]);
        return $this->respondSuccess();
    }

    public function updateMailchimp(Request $request)
    {
        if (!$request->has('mailchimp')) {
            return $this->respondError("no mailchimp data present", 400);
        }
        $mailchimpIntegration = user()
            ->integrations()
            ->where('service', 'mailchimp')
            ->firstOrFail();
        $mailchimpIntegration->update([
            'remote_id' => $request->input('mailchimp.key')
        ]);
        return $this->respondSuccess();
    }

    public function importMailchimp(Request $request)
    {
        $mailchimpIntegration = user()
            ->integrations()
            ->where('service', 'mailchimp')
            ->firstOrFail();

        if (!$mailchimpIntegration) {
            return $this->respondError('Mailchimp integration not found', 404);
        }

        if (!\App::environment('local')) {
            ImportMailchimpContacts::dispatch($mailchimpIntegration);
        } else {
            $mailchimpIntegration->importMailchimpContacts();
        }

        return $this->respondSuccess();
    }

    /**
     * Build authentication URL for our app.
     *
     * @param $service
     *
     * @return JsonResponse
     */
    public function oauthRedirect(string $service): JsonResponse
    {
        switch ($service) {
            case Integration::SERVICE_HUBSPOT: {
                    $authenticationUrl = Socialite::driver($service)
                        ->stateless()
                        // A space separated set of scopes that our app will need access to.
                        // Any scopes that you have checked in your app settings will be treated as required scopes,
                        // and you'll need to include any selected scopes in this parameter or
                        // the authorization page will display an error.
                        ->with(['scope' => 'contacts'])
                        ->redirect()
                        ->getTargetUrl();

                    break;
                }

            case Integration::SERVICE_FREEE: {
                    $authenticationUrl = Socialite::driver($service)
                        ->stateless()
                        ->with(['access_type' => 'offline'])
                        ->redirect()
                        ->getTargetUrl();

                    break;
                }

            case Integration::SERVICE_GOOGLE: {
                    $authenticationUrl = Socialite::driver($service)
                        ->redirectUrl(config('services.google.redirect_for_integration'))
                        ->with(['access_type' => 'offline', 'approval_prompt' => 'force'])
                        ->scopes(['openid', 'profile', 'email', Google_Service_People::CONTACTS_READONLY])
                        ->stateless()
                        ->redirect()
                        ->getTargetUrl();

                    break;
                }

            default: {
                    logger()->info('Unexpected service type.', ['service' => $service]);
                    return $this->respondInternalError('Invalid service type.');
                }
        }

        return $this->respond(['url' => $authenticationUrl]);
    }

    /**
     *
     *
     * @param Request $request
     * @param string  $service
     *
     * @return JsonResponse
     */
    public function oauthCallback(Request $request, string $service): JsonResponse
    {
        try {
            switch ($service) {
                case Integration::SERVICE_HUBSPOT: {
                        /**
                         * Should contain 'access_token', 'refresh_token' and 'expires_in' keys.
                         * @var array $response
                         */
                        $response = Socialite::with(Integration::SERVICE_HUBSPOT)
                            ->getAccessTokenResponse($request->get('code'));

                        $this->createIntegration($service, $request->user()->email, $response);

                        return $this->respondCreated(['status' => 'success']);
                    }

                case Integration::SERVICE_FREEE: {
                        $response = Socialite::driver($service)
                            ->stateless()
                            ->user();

                        $freee = $this->createIntegration($service, $response->getId(), [$response]);

                        if (!\App::environment('local')) {
                            ImportFreee::dispatch($freee);
                        } else {
                            $freee->importFreee();
                        }

                        break;
                    }

                case Integration::SERVICE_GOOGLE: {
                        $response = Socialite::driver($service)
                            ->redirectUrl(config('services.google.redirect_for_integration'))
                            ->stateless()
                            ->user();

                        if (!$remoteId = $response->getId()) {
                            return $this->respondError('No user id in oauth response.', Response::HTTP_UNPROCESSABLE_ENTITY);
                        }

                        $integration = $request->user()->integrations()->updateOrCreate(
                            [
                                'remote_id' => $remoteId,
                                'service'   => $service,
                            ],
                            [
                                'details' => $response,
                            ]
                        );

                        return $this->respondCreated(['status' => 'success']);
                    }

                default: {
                        throw new \Exception('Invalid service type: ' . $service);
                    }
            }
        } catch (\Exception $e) {
            info($e->getMessage());
            return $this->respondError(Lang::getFromJson('No user in oauth response'), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->respondSuccess();
    }

    /**
     * Create integration for service.
     *
     * @param string      $service
     * @param string|null $remoteId
     * @param array       $details
     *
     * @return Integration
     */
    private function createIntegration(string $service, ?string $remoteId, array $details = []): Integration
    {
        return user()->integrations()->create([
            'remote_id' => $remoteId,
            'service'   => $service,
            'details'   => $details
        ]);
    }

    public function updateFreee()
    {
        $freee = user()
            ->integrations()
            ->where('service', 'freee')
            ->firstOrFail();

        if (!array_has($freee->details, 'token')) {
            return $this->respondError('no auth token');
        }
        // TODO: what if user has multiple companies?
        if (!array_has($freee->details, 'user.companies.0.id')) {
            return $this->respondError('no companies on file');
        }
        if (!\App::environment('local')) {
            ImportFreee::dispatch($freee);
        } else {
            $freee->importFreee();
        }
        return $this->respondSuccess();
    }

    /**
     * Creates a job to import contacts from google.
     *
     * @return JsonResponse
     */
    public function importGoogleContacts(): JsonResponse
    {
        /** @var Integration $integration */
        $integration = user()
            ->integrations()
            ->where('service', Integration::SERVICE_GOOGLE)
            ->firstOrFail();

        if (!\App::environment('local')) {
            ImportGoogleContacts::dispatch($integration);
        } else {
            $integration->importGoogleContacts();
        }

        return $this->respondSuccess();
    }

    /**
     * Creates a job to import contacts from HubSpot.
     *
     * @return JsonResponse
     */
    public function importHubSpotContacts(): JsonResponse
    {
        /** @var Integration $integration */
        $integration = user()
            ->integrations()
            ->where('service', Integration::SERVICE_HUBSPOT)
            ->firstOrFail();

        if (!\App::environment('local')) {
            ImportHubSpotContacts::dispatch($integration);
        } else {
            $integration->importHubSpotContacts();
        }

        return $this->respondSuccess();
    }
}
