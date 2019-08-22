<?php

namespace App\Http\Transformers;

class ContactTransformer extends Transformer
{
    protected $resourceName = 'contact';

    public function transform($data)
    {
        $customer = $data['stripe_customer'];
        $contact = [
            'id' => $data['id'],
            'email' => $data['email'],
            'name' => $data['name'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name_katakana' => $data['name_katakana'],
            'gender' => $data['gender'],
            'industry_id' => $data['industry_id'],
            'size' => $data['size'],
            'contact_image' => $data['contact_image'],

            'tags' => $data['tagArray'],
            'meta' => $data['meta'],

            'subscribed' => $data->subscribed(),
            'card_last_four' => array_get($customer, 'card_last_four'),
            'card_brand' => array_get($customer, 'card_brand'),
            'trial_ends_at' => array_get($customer, 'trial_ends_at'),

            'status' => $data['status'],
            'language' => $data['language'],
            'accepts_marketing' => $data['accepts_marketing'],
            'is_company' => $data['is_company'],
            'is_vendor' => $data['is_vendor'],

            'notes' => $data['notes'],
            'website' => $data['website'],
            'phone' => $data['phone'],
            'source' => $data['source'],
            'birthday' => $data['birthday'] ? $data['birthday']->format("Y-m-d") : null,
            'last_visit_at' => array_get($customer, 'last_visit_at', null),
            'last_contact_at' =>
                $data['last_contact_at']
                    ? $data['last_contact_at']->format("Y-m-d H:i:s")
                    : null,
            'registered_at' =>
                $data['registered_at'] ? $data['registered_at']->format("Y-m-d H:i:s") : null,
            'created_at' => $data['created_at']->format("Y-m-d H:i:s"),
            'updated_at' => $data['updated_at']->format("Y-m-d H:i:s"),
            'deleted_at' =>
                $data['deleted_at'] ? $data['deleted_at']->format("Y-m-d H:i:s") : null
        ];

        $arr = $data->toArray();

        if (isset($arr['assignee'])) {
            $contact['assigned_to'] = [
                'id' => $arr['assigned_to'],
                'contact_image' => $arr['contact_image'],
                'name' => $arr['assignee']['name'],
                'email' => $arr['assignee']['email']
            ];
        }
        if ($arr['is_company']) {
            $contact['contacts'] = $data['contacts']->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name
                ];
            });
        }

        if (isset($arr['company'])) {
            $contact['company'] = [
                'id' => $arr['company_id'],
                'contact_image' => $arr['contact_image'],
                'name' => $arr['company']['name'],
                'email' => $arr['company']['email'],
                'phone' => $arr['company']['phone']
            ];
        }

        if (isset($arr['parent'])) {
            $contact['parent'] = [
                'id' => $arr['company']['parent_id'],
                'contact_image' => $arr['contact_image'],
                'name' => $arr['parent']['name'],
                'email' => $arr['parent']['email']
            ];
        }

        if (isset($arr['addresses'])) {
            $contact['addresses'] = $arr['addresses'];
        }
        return $contact;
    }
}
