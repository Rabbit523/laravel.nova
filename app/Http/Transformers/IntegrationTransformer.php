<?php

namespace App\Http\Transformers;

class IntegrationTransformer extends Transformer
{
    protected $resourceName = 'integration';

    public function transform($data)
    {
        $integration = [
            'id' => $data['id'],
            'service' => $data['service'],
            'created_at' => $data['created_at']->format("Y-m-d"),
            'last_updated_at' =>
                $data['last_updated_at']
                    ? $data['last_updated_at']->format("Y-m-d H:i:s")
                    : false,
            'status' => $data['last_status'],
            'error' => array_get($data, 'details.error')
        ];

        if ($integration['service'] == 'webhook') {
            $integration['url'] = $data['remote_id'];
            $integration['enabled'] = $data['details']['enabled'];
        } elseif ($integration['service'] == 'api') {
            $integration['key'] = $data['remote_id'];
        } elseif ($integration['service'] == 'stripe') {
            $integration['key'] = $data['remote_id'];
            $integration['secret'] = array_get($data, 'details.secret');
            $integration['project'] = array_get($data, 'details.project');
        } elseif ($integration['service'] == 'microsoft') {
            $integration['user'] = [];
            $integration['user']['name'] = array_get($data, 'details.displayName');
            $integration['user']['avatar'] = array_get($data, 'details.avatar');
            $integration['user']['email'] = array_get($data, 'details.userPrincipalName');
        } elseif ($integration['service'] == 'google') {
            $integration['user'] = [];
            $integration['user']['name'] = array_get($data, 'details.name');
            $integration['user']['avatar'] =
                array_get($data, 'details.avatar_original') . '?sz=150';
            $integration['user']['email'] = array_get($data, 'details.email');
        } elseif ($integration['service'] == 'mailchimp') {
            $integration['key'] = $data['remote_id'];
        } elseif ($integration['service'] == 'freee') {
            // $integration['key'] = $data['remote_id'];
            $integration['user'] = ['companies' => [], 'balance' => null];
            $integration['user']['companies'] = array_get($data, 'details.companies', []);
            $integration['user']['balance'] = array_get($data, 'details.balance', null);
        }
        // $data = $data->toArray();
        return $integration;
    }
}
