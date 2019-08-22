<?php

namespace App\Http\Controllers\Api;

use App\Services\Stripe\StripeBalanceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Lang;

class ConnectController extends Controller
{
    /**
     * Create a Connect controller instance.
     *
     * @return void
     */
    public function __construct(StripeBalanceService $stripeBalanceService)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        $this->middleware('has.connect')->except(['store']);
        $this->balance_service = $stripeBalanceService;
    }

    public function store(Request $request)
    {
        if (user()->connect_id) {
            return response()->json(
                ["error" => ["message" => 'Connect already enabled on account']],
                400
            );
        }
        $account_token = $request->input('account');
        $person_token = $request->input('person');
        if (!$account_token) {
            return response()->json(["error" => ["message" => "Missing account token"]], 403);
        }
        try {
            $account = \Stripe\Account::create([
                'country' => 'JP',
                'type' => 'custom',
                "account_token" => $account_token,
            ]);
        } catch (\Exception $e) {
            log_error($e);
            return response()->json(["error" => ["message" => $e->getMessage()]], 403);
        }

        user()->connect_id = array_get($account, 'id', $account->id);
        if (is_null(user()->sender_address)) {
            user()->sender_address = str_random(10);
        }
        user()->save();
        try {
            $person = \Stripe\Account::createPerson(user()->connect_id, [
                'person_token' => $person_token,
            ]);
        } catch (\Exception $e) {
            log_error($e);
            return response()->json(["error" => ["message" => "Invalid personal token"]], 403);
        }
        return ok();
    }

    public function show(Request $request)
    {
        // TODO: show connect account status?
        return ok();
    }

    public function update(Request $request)
    {
        $account_token = $request->input('account');
        $person_token = $request->input('person');

        if ($account_token) {
            try {
                $account = \Stripe\Account::update(user()->connect_id, [
                    "account_token" => $account_token,
                ]);
            } catch (\Exception $e) {
                log_error($e);
                return response()->json(["error" => ["message" => $e->getMessage()]], 403);
            }
        }

        if ($person_token) {
            try {
                $persons = \Stripe\Account::allPersons(user()->connect_id, [
                    'relationship' => [
                        'account_opener' => true,
                    ],
                ]);

                $opener = current($persons->data);
                $person = \Stripe\Account::updatePerson(user()->connect_id, $opener->id, [
                    'person_token' => $person_token,
                ]);
            } catch (\Exception $e) {
                log_error($e);
                return response()->json(
                    ["error" => ["message" => "Invalid personal token"]],
                    403
                );
            }
        }
        return ok();
    }

    public function showVerification()
    {
        return response()->json(["verification" => user()->verification_status], 200);
    }

    public function sendVerification(Request $request)
    {
        $token = $request->input('token');
        $persons = \Stripe\Account::allPersons(user()->connect_id, [
            'relationship' => [
                'account_opener' => true,
            ],
        ]);

        $opener = current($persons->data);
        $person = \Stripe\Account::updatePerson(user()->connect_id, $opener->id, [
            'person_token' => $token,
        ]);

        return ok();
    }

    public function updateBankDetails(Request $request)
    {
        $token = $request->input('token');

        $account = \Stripe\Account::update(user()->connect_id, [
            "external_account" => $token,
        ]);

        return ok();
    }

    public function currentBalance()
    {
        try {
            return response()->json([
                'balance' => $this->balance_service->getCurrentBalance(),
            ]);
        } catch (\Stripe\Error\Base $e) {
            return response()->json(["error" => ["message" => $e->getMessage()]], 400);
        } catch (\Exception $e) {
            log_error($e);
            return response()->json(
                ["error" => ["message" => Lang::getFromJson('Error showing balance.')]],
                500
            );
        }
    }

    public function balanceHistory()
    {
        try {
            $limit = 5;

            return response()->json([
                'balance' => $this->balance_service->getBalanceHistory($limit),
            ]);
        } catch (\Stripe\Error\Base $e) {
            return response()->json(["error" => ["message" => $e->getMessage()]], 400);
        } catch (\Exception $e) {
            log_error($e);
            return response()->json(
                [
                    "error" => [
                        "message" => Lang::getFromJson('Error showing balance history.'),
                    ],
                ],
                500
            );
        }
    }
}
