<?php

namespace App\Http\Requests\Api;

class UpdateUser extends ApiRequest
{
    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData()
    {
        return $this->get('user') ?: [];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // 'email' => 'sometimes|email|max:255|unique:users,email,' . $this->user()->id,
            'name' => 'required|string|max:40',
            'password' => 'sometimes|min:6'
        ];
    }
}
