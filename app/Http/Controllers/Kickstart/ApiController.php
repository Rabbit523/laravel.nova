<?php

namespace App\Http\Controllers\Kickstart;

use App\Http\Controllers\ApiController as BaseApiController;

use App\Project;
use App\Customer;
use App\Services\AWS\AwsS3Service;

use Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

use Thenextweb\PassGenerator;
use Thenextweb\Definitions\StoreCard;
use Thenextweb\Definitions\Dictionary\Barcode;
use Thenextweb\Definitions\Dictionary\Date as PassDate;
use Thenextweb\Definitions\Dictionary\Field as PassField;

use App\Notifications\KickstartAccountCreated as KickstartAccountCreatedNotification;

// use App\Notifications\AccountCreated as AccountCreatedNotification;

class ApiController extends BaseApiController
{
    use AuthenticatesUsers, RegistersUsers {
    AuthenticatesUsers::redirectPath insteadof RegistersUsers;
    }

    protected $guard = 'customers';

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct()
    {
        $accept_language = request()->server('HTTP_ACCEPT_LANGUAGE');
        if ($accept_language && substr($accept_language, 0, 2) == 'ja') {
            \App::setLocale('ja');
        }
        Auth::setDefaultDriver('customers');
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('customers');
    }

    public function username()
    {
        return 'email';
    }

    public function invoices(Project $project)
    {
        return $this->respond([
            'invoices' => auth('customers')->user()->invoices,
        ]);
    }

    public function profile(Project $project)
    {
        return $this->respondProfile();
    }

    private function respondProfile()
    {
        $user = auth('customers')->user();
        $subscription = $user->subscription();
        return $this->respond(compact('user'));
    }

    /**
     * Handle a subscription request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param \App\Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request, Project $project)
    {
        $project_id = array_get($request->route()->parameters(), 'project', false);
        if (!$project->id) {
            $project = Project::where('id', $project_id)->firstOrFail();
        }
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        $customer = user();
        $plan_id = request()->input('plan_id');
        $token = request()->input('token');
        $coupon = request()->input('coupon');

        if (!$project->beacon || !$project->beacon->is_enabled) {
            return $this->respondError(Lang::getFromJson("Subscribe not allowed"));
        }
        $beacon_plans = $project->beacon->plans;
        $plans = $project->plans->whereIn('id', collect($project->beacon->plans)->flatten());
        $plan = $plans->firstWhere('id', $plan_id);

        if (!$plan) {
            return $this->respondError(Lang::getFromJson("Plan not found"));
        }
        if (!$plan->payment_id) {
            error('kickstart subscribe', [$plan->id, 'Plan without payment id']);
            return $this->respondError(Lang::getFromJson("Plan not found"));
        }

        // TODO: check for additional items in the plan
        // add them to invoice tab:
        //  $customer->tab(name, amount)

        $subscription = $customer->newSubscription($project->id, $plan->payment_id);
        // TODO: add ability to get the application from plan meta
        $subscription->setApplicationFeePercent(config('services.stripe.application_fee', 0));

        if (!empty($coupon)) {
            $subscription->withCoupon($coupon);
        }

        $subscription->skipTrial();
        $subscription->create($token ?? null);
        // TODO: add links to passes here
        // $customer->notify(new KickstartAccountCreatedNotification());
        return $this->respond(compact('subscription'));
    }

    public function cancelSubscription(Project $project)
    {
        $user = auth('customers')->user();
        if (!$user->stripe_id) {
            return $this->respond([
                'cost' => 0,
                'error' => Lang::getFromJson('invalid customer'),
            ]);
        }

        $user->subscription($project->id)->cancel();
        // event(new SubscriptionCancelled($request->user()->fresh()));
        return $this->respondSuccess();
    }

    public function resumeSubscription(Project $project)
    {
        $user = auth('customers')->user();
        if (!$user->stripe_id) {
            return $this->respond([
                'cost' => 0,
                'error' => Lang::getFromJson('invalid customer'),
            ]);
        }
        $user->subscription($project->id)->resume();
        // event(new SubscriptionCancelled($request->user()->fresh()));
        return $this->respondSuccess();
    }

    public function changeCard(Project $project)
    {
        $user = auth('customers')->user();
        $token = request()->input('token');
        if (!$token) {
            return $this->respondError(Lang::getFromJson('Card token is required.'));
        }
        $user->updateCard($token);
        return $this->respondProfile();
    }

    public function changeSubscription(Project $project)
    {
        $user = auth('customers')->user();
        $subscription = $user->subscription($project->id);

        $plan_id = request()->input('plan_id');
        $coupon = request()->input('coupon');

        if (!$subscription && !$plan_id) {
            return $this->respondError(Lang::getFromJson('No plan or previous subscription.'));
        }

        $beacon_plans = $project->beacon->plans;
        $plans = $project->plans->whereIn('id', collect($project->beacon->plans)->flatten());
        $plan = $plans->firstWhere('payment_id', $plan_id);

        if (!$plan->payment_id) {
            return $this->respondError(Lang::getFromJson('Invalid plan'));
        }
        if ($subscription->stripe_plan != $plan->payment_id) {
            if (!empty($coupon)) {
                $subscription->withCoupon($coupon);
            }
            $subscription->noProrate()->swap($plan->payment_id);
            // $user->notify(new SubscriptionChangedNotification($plan->name));
        }
        return $this->respond(compact('subscription'));
    }

    // auth

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        return $request->only('email', 'password');
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();
        $this->clearLoginAttempts($request);

        return $this->respondProfile();
    }

    /**
     *
     * @param  \Illuminate\Http\Request  $request
     * @param \App\Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request, Project $project)
    {
        $this->validateLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }

        $this->incrementLoginAttempts($request);
        return $this->respondFailedLogin();
    }

    public function logout(Project $project)
    {
        Auth::guard('customers')->logout();
        return ok();
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'email' => 'required|string|email|max:255',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6',
        ]);
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request, Project $project)
    {
        $project_id = array_get($request->route()->parameters(), 'project', false);
        if (!$project->id) {
            $project = Project::where('id', $project_id)->firstOrFail();
        }

        $credentials = $request->all();
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return $this->respondError("missing email or password", 400);
        }

        $user = Customer::where('email', $credentials['email'])->first();
        if ($user) {
            return $this->login($request, $project);
        }

        $this->validator($credentials)->validate();

        try {
            $user = $this->create($credentials, $project);
        } catch (\Exception $e) {
            return $this->respondError($e->getMessage());
        }
        $this->guard()->login($user);
        return $this->registered($request, $user) ?: $this->respondProfile();
    }

    /**
     * Create a new customer after a valid registration.
     *
     * @param  array  $data
     * @param \App\Project $project
     * @return \App\Customer
     */
    protected function create(array $data, Project $project)
    {
        if (!$project->id) {
            throw new \Exception("no project id");
        }
        $contact = $project->user->contacts()->firstOrCreate(
            [
                'email' => $data['email'],
            ],
            [
                'email' => $data['email'],
                'last_name' => $data['name'],
                'registered_at' => now(),
                'accepts_marketing' => true,
                'is_company' => false,
                'is_vendor' => false,
                'source' => 'kickstart',
                'status' => 'customer',
            ]
        );
        $contact->registered_at = now();
        $contact->status = 'customer';
        $contact->save();

        // TODO: generate password and send to email
        $customer = $contact->customers()->create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // $customer->notify(new KickstartAccountCreatedNotification());
        return $customer;
    }

    /**
     * Handle a download invoice request.
     *
     * @param  \App\Project $project
     * @param  string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadInvoice(Project $project, string $id)
    {
        return user()->downloadInvoice($id, [
            'product' => 'invoice',
            'vendor' => user()->user->email
        ]);
    }

    public function downloadIosPass()
    {
        $pass_identifier = str_replace('-', '', auth()->id());
        // $pkpass = PassGenerator::getPass($pass_identifier);
        // if (!$pkpass) {
        $pkpass = $this->createWalletPass($pass_identifier);
        // }

        return new Response($pkpass, 200, [
            'Content-Transfer-Encoding' => 'binary',
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => 'attachment; filename="pass.pkpass"',
            'Content-length' => strlen($pkpass),
            'Content-Type' => PassGenerator::getPassMimeType(),
            'Pragma' => 'no-cache',
        ]);
    }

    private function createWalletPass($pass_identifier)
    {
        $passgenerator = new PassGenerator($pass_identifier, true);
        $owner = user()->user;

        $card = new StoreCard();
        $card->setDescription('Membership Card');
        $logo_text = 'Kinchaku';
        if (array_has($owner->settings, 'brand_name')) {
            $logo_text = array_get($owner->settings, 'brand_name');
        }
        $card->setLogoText($logo_text);
        $card->setOrganizationName($logo_text);
        $card->setSerialNumber(make_UUID());
        // TODO: set expiration date based on plan?
        // $pass->setExpirationDate(now()->addMonths(6));
        $barcode = new Barcode(
            config('app.url') . '/contacts/' . user()->contact_id,
            Barcode::FORMAT_QR
        );
        $card->addBarcode($barcode);
        // $card->addPrimaryField(new PassDate('created_at', now(), [
        //     'dateStyle' => PassDate::STYLE_FULL,
        // ]));

        // $card->addPrimaryField(new PassField(
        //     'member',
        //     user()->email
        // ));

        $card->addHeaderField(new PassField(
            'status',
            Lang::getFromJson('MEMBER'),
            ['label' => Lang::getFromJson('STATUS')]
        ));
        $card->addHeaderField(new PassField(
            'points',
            42,
            ['label' => Lang::getFromJson('POINTS')]
        ));

        if (user()->contact->getName()) {
            $card->addSecondaryField(new PassField(
                'deal',
                user()->contact->getName(),
                ['label' => Lang::getFromJson('Name')]
            ));
        }
        $card->addSecondaryField(new PassField(
            'subtitle',
            user()->created_at->year,
            ['label' => Lang::getFromJson('MEMBER SINCE')]
        ));

        $card->setBackgroundColor(
            array_get($owner->settings, 'primary_color', '#7a80da')
        );
        $card->setForegroundColor(
            array_get($owner->settings, 'secondary_color', 'rgb(255,255,255)')
        );
        $logo = base_path('resources/assets/wallet/logo.png');
        $icon = base_path('resources/assets/wallet/icon.png');

        $awsS3Service = (new AwsS3Service())->setUser($owner)->setBucketName();

        $result = Storage::makeDirectory('wallet/' . $pass_identifier);
        if (!$result) {
            throw new \Exception("Can not create wallet storage");
        }
        if ($awsS3Service->exists(array_get($owner->settings, 'logo_path'))) {
            $file = array_get($owner->settings, 'logo_path');
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $logo = "wallet/$pass_identifier/logo.$ext";
            $awsS3Service->copy($file, $logo);
            $logo = storage_path("app/$logo");
        }

        if ($awsS3Service->exists(array_get($owner->settings, 'icon_path'))) {
            $file = array_get($owner->settings, 'icon_path');
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $icon = "wallet/$pass_identifier/icon.$ext";
            $awsS3Service->copy($file, $icon);
            $icon = storage_path("app/$icon");
        } else {
            $passgenerator->addAsset(base_path('resources/assets/wallet/icon@2x.png'));
        }
        if ($awsS3Service->exists(array_get($owner->settings, 'strip_path'))) {
            $strip = $awsS3Service->path(array_get($owner->settings, 'strip_path'));
            $passgenerator->addAsset($strip, 'strip');
        } else {
            $passgenerator->addAsset(base_path('resources/assets/wallet/strip.png'));
            // $passgenerator->addAsset(base_path('resources/assets/wallet/strip@2x.png'));
        }
        $passgenerator->addAsset($logo);
        $passgenerator->addAsset($icon);

        // test icons and strip
        // $passgenerator->addAsset(base_path('resources/assets/wallet/icon@2x.png'));

        $passgenerator->setPassDefinition($card);
        return $passgenerator->create();
    }
}
