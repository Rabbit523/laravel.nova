<?php

namespace App\Http\Requests\Api;

class CreateContact extends ApiRequest
{
    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData()
    {
        return $this->get('contact') ?: [];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'email' => 'nullable|email',
            'status' => 'required|string',
            'name_katakana' => 'nullable|string',
            'birthday' => 'nullable|date',
            'website' => 'nullable|string',
            'phone' => 'nullable|numeric',
            'is_company' => 'sometimes|boolean',
            'accepts_marketing' => 'sometimes|boolean',
            'meta' => 'sometimes|array'
        ];
    }
}
