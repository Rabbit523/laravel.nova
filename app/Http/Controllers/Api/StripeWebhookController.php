<?php

namespace App\Http\Controllers\Api;

use App\Http\Middleware\VerifyWebhookSignature;
use App\Webhook;
use App\User;
use App\Jobs\ProcessWebhook;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeWebhookController extends WebhookController
{
    /**
     * @inheritdoc
     */
    public function __construct()
    {
        $this->middleware(VerifyWebhookSignature::class);
    }

    /**
     * @inheritdoc
     */
    public function handleWebhook(Request $request, $key = false)
    {
        $payload = json_decode($request->getContent(), true);

        $data = [];
        $data['service'] = 'stripe';
        $data['type'] = $payload['type'];
        $data['payload'] = $payload;
        $integration = null;

        switch ($key) {
            case 'connect':
                $data['service'] = 'connect';
                $user = User::where('connect_id', array_get($payload, 'account'))->first();
                if ($user) {
                    $data['owner_id'] = $user->id;
                }
                $webhook = Webhook::create($data);
                break;
            default:
                $integration = get_integration($key, 'stripe');
                $project = false;
                if ($integration) {
                    $data['owner_id'] = $integration->user_id;
                    $webhook = $integration->webhooks()->create($data);
                } else {
                    info('webhook without integration', ['stripe', $payload['type'], $key]);
                    $webhook = Webhook::create($data);
                    return response('Webhook Handled', 200);
                }
                break;
        }

        if (!\App::environment('local')) {
            ProcessWebhook::dispatch($webhook);
        } else {
            $webhook->parse($integration);
        }

        return response('Webhook Handled', 200);
    }
}
