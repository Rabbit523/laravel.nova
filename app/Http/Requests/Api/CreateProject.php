<?php

namespace App\Http\Requests\Api;

class CreateProject extends ApiRequest
{
    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData()
    {
        return $this->get('project') ?: [];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'url' => 'nullable|string|max:255',
            'currency' => 'required|string',
            'start_date' => 'required|date'
        ];
    }
}
