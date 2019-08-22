<?php

namespace App\Http\Requests\Api;

class UpdateUserCompany extends ApiRequest
{
    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData()
    {
        return $this->get('company') ?: [];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // 'name' => 'required|string|max:40',
            'fiscal_start' => 'sometimes|integer|min:0|max:11',
            'fiscal_end' => 'sometimes|integer|min:0|max:11',
        ];
    }
}
