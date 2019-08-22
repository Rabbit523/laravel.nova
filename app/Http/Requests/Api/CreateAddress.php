<?php

namespace App\Http\Requests\Api;

class CreateAddress extends ApiRequest
{
    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData()
    {
        return $this->get('address') ?: [];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'role' => 'nullable|string|max:150',
            'street' => 'nullable|string|max:150',
            'city' => 'nullable|string|max:150',
            'state' => 'nullable|string|max:150',
            'country' => 'nullable|alpha|size:2',
            'postcode' => 'nullable|string|max:150',
            'phone' => 'nullable|numeric'
        ];
    }
}
