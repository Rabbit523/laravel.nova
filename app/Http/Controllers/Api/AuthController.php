<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\User;
use App\Integration;
use App\Jobs\ExportContact;
use App\Http\Requests\Api\LoginUser;
use App\Notifications\AccountCreated as AccountCreatedNotification;
use Google_Service_People;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Foundation\Auth\ResetsPasswords;

use Newsletter;
use Socialite;

class AuthController extends ApiController
{
    use ResetsPasswords, SendsPasswordResetEmails {
    ResetsPasswords::broker insteadof SendsPasswordResetEmails;
    }

    public function username()
    {
        return 'email';
    }

    /**
     * Login user and return the user if successful.
     *
     * @param LoginUser $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginUser $request)
    {
        if (!$request->has('user')) {
            return $this->respondError("no user data present", 400);
        }

        $credentials = $request->get('user');
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return $this->respondError("no user data present", 400);
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();

        if (!Hash::check($credentials['password'], $user->password)) {
            return $this->respondFailedLogin();
        }

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        $user->last_visit_at = now();
        $user->save();

        return response(['token' => $token], 200);
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
            'email' => 'required|string|email|max:255|unique:users',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6'
        ]);
    }

    public function register(LoginUser $request)
    {
        $this->validator($request->get('user'))->validate();

        event(new Registered($user = $this->create($request->get('user'))));

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        return response(['token' => $token], 200);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {
        $lang = array_get($data, 'language', 'ja');
        $data['language'] = $lang == 'undefined' ? 'ja' : $lang;
        $user = User::create([
            'email' => $data['email'],
            'name' => $data['name'],
            'password' => Hash::make($data['password']),
            'language' => $data['language'],
            'last_visit_at' => now(),
            'on_mail_list' => true
        ]);
        $user->notify(new AccountCreatedNotification());
        if (\App::environment('prod')) {
            Newsletter::subscribe($user->email, ['NAME' => $user->name]);
        }

        if (!\App::environment('local') && config('services.kinchaku.key')) {
            ExportContact::dispatch($data);
        }
        return $user;
    }

    public function registerOauth($service, $data)
    {
        $email = $data->getEmail() ?: $data->user['userPrincipalName'];
        if (!$email) {
            return $this->respondError(Lang::getFromJson("No email in oauth response"), 422);
        }
        event(
            new Registered(
                $user = $this->create([
                    'email' => $email,
                    'name' => $data->getName(),
                    'password' => Hash::make($data->getId())
                ])
            )
        );
        $user->integrations()->create([
            'remote_id' => $data->getId(),
            'service' => $service,
            'details' => $data
        ]);

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        return response(['token' => $token], 200);
    }

    public function callback($service)
    {
        // $user = Socialite::driver($service)->userFromToken($token);
        try {
            $user = Socialite::driver($service)
                ->stateless()
                ->user();
        } catch (\Exception $e) {
            return $this->respondError(Lang::getFromJson("No user in oauth response"), 422);
        }

        $remote_id = $user->getId();
        debug('remote_id', [$service => $remote_id]);

        if (!$remote_id) {
            return $this->respondError(Lang::getFromJson("No user id in oauth response"), 422);
        }

        $integration = Integration::where('remote_id', $remote_id)
            ->where('service', $service)
            ->first();

        if (!$integration) {
            $user['language'] = request()->get('language', 'ja');
            $email = $user->getEmail() ?: array_get($user->user, 'userPrincipalName');
            $account = auth()->check() ? user() : User::where('email', $email)->first();

            if (!$account) {
                return $this->registerOauth($service, $user);
            }
            $integration = $account->integrations()->create([
                'remote_id' => $user->getId(),
                'service' => $service,
                'details' => $user
            ]);
            if (auth()->check()) {
                return $this->respond(compact('integration'), 201);
            }
        }

        $integration->user->last_visit_at = now();
        $integration->user->save();

        $token = $integration->user->createToken('Laravel Password Grant Client')->accessToken;
        return $this->respond(compact('token'));
    }

    /**
     * @param $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function redirect($service)
    {
        if (Integration::SERVICE_GOOGLE === $service) {
            $response = $this->googleRedirect();
        } else {
            $response = $this->defaultRedirect($service);
        }

        return $this->respond([
            'url' => $response->getTargetUrl()
            // 'state' => Socialite::driver($service)->getState()
        ]);
    }

    /**
     * @param $service
     *
     * @return mixed
     */
    private function defaultRedirect($service)
    {
        return Socialite::driver($service)
            ->stateless()
            ->redirect();
    }

    /**
     * Refreshing an access token (offline access).
     * https://developers.google.com/identity/protocols/OAuth2WebServer#offline
     *
     * @return mixed
     */
    private function googleRedirect()
    {
        return Socialite::driver(Integration::SERVICE_GOOGLE)
            ->with(['access_type' => 'offline', 'approval_prompt' => 'force'])
            ->scopes(['openid', 'profile', 'email', Google_Service_People::CONTACTS_READONLY])
            ->stateless()
            ->redirect();
    }

    /**
     * Get the response for a successful password reset link.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\JsonResponse
     */
    protected function sendResetLinkResponse(Request $request, $response)
    {
        return $this->respondMessage(trans($response));
    }

    /**
     * Get the response for a failed password reset link.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\JsonResponse
     */
    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        return $this->respondError(trans($response), 422);
    }

    /**
     * Get the response for a successful password reset.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\JsonResponse
     */
    protected function sendResetResponse(Request $request, $response)
    {
        $token = user()->createToken('Laravel Password Grant Client')->accessToken;
        return response(['token' => $token], 200);
    }

    /**
     * Get the response for a failed password reset.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\JsonResponse
     */
    protected function sendResetFailedResponse(Request $request, $response)
    {
        return $this->respondError(trans($response), 422);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->token();
        $token->revoke();

        $response = ['message' => 'You have been succesfully logged out!'];
        return response($response, 200);
    }
}
