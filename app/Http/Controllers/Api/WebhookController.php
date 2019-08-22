<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Integration;

use Illuminate\Http\Request;

class WebhookController extends ApiController
{
    /**
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleWebhook(Request $request)
    {
        //TODO: save all incoming (and outgoing...) webhooks
        $remote_id = $request->input('key');
        $integration = Integration::where('remote_id', $remote_id)
            ->where('service', 'api')
            ->first();
        if (!$integration) {
            return $this->respondError('Unknown API key', 403);
        }
        // if (!$integration->user->subscribed('default')) {
        //     return $this->respondError('Payment required', 402);
        // }
        if ($request->input('event') != 'add_customer') {
            $integration->setLastStatus(
                'failed',
                'Not supported event: ' . $request->input('event')
            );
            return $this->respondError('Not supported event');
        }
        $data = [];
        $input = $request->input('payload');
        $data['source'] = 'api';
        $data['name'] = array_get($input, 'name');
        $data['first_name'] = array_get($input, 'first_name');
        $data['last_name'] = array_get($input, 'last_name');
        $data['email'] = array_get($input, 'email');
        $data['language'] = array_get($input, 'language', 'ja');
        $data['accepts_marketing'] = array_get($input, 'accepts_marketing', true);
        $data['is_company'] = array_get($input, 'is_company', false);
        $data['created_at'] = array_get($input, 'created_at', now());
        $data['status'] = array_get($input, 'status', 'new');
        if (
            (!$data['name'] && !$data['first_name'] && !$data['last_name']) &&
            !$data['email']
        ) {
            $integration->setLastStatus('failed', 'Missing required email/name fields');
            return $this->respondError('Missing required email/name fields');
        }
        if (
            $integration->user->contacts()
                ->where('email', $data['email'])
                ->first()
        ) {
            $integration->setLastStatus('failed', 'Already exists: ' . $data['email']);
            return $this->respondError('Already exists');
        }
        $customer = $integration->user->contacts()->create($data);
        $integration->setLastStatus('success');

        return $this->respondCreated(compact('customer'));
    }
}
