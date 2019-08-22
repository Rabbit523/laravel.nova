<?php

namespace App\Http\Transformers;

class UserTransformer extends Transformer
{
    protected $resourceName = 'user';

    public function transform($data)
    {
        return [
            'id' => $data['id'],
            'email' => $data['email'],
            'name' => $data['name'],
            'language' => $data['language'],
            'on_trial' => $data->onGenericTrial(),
            'trial_ends_at' =>
            $data->onGenericTrial() ? $data['trial_ends_at']->format("Y-m-d") : null,
            'trial_ended' => $data->trialEnded(),
            'card_last_four' => $data['card_last_four'],
            'card_brand' => $data['card_brand'],
            'on_mail_list' => $data['on_mail_list'],
            'connect_id' => $data['connect_id'],
            'sender_address' => $data['sender_address'],
            'settings' => $data['settings'],
        ];
    }
}
