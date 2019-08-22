<?php

namespace App\Http\Transformers;

class ProfileTransformer extends Transformer
{
    protected $resourceName = 'profile';

    public function transform($data)
    {
        return [
            'name' => $data['name']
        ];
    }
}
