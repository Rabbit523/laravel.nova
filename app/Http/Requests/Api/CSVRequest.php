<?php

namespace App\Http\Requests\Api;

class CSVRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'file' => 'required|mimes:csv,txt',
        ];
    }
}
