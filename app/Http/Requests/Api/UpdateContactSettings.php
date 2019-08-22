<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactSettings extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'settings' => 'required|array',
            'settings.icon_file' => 'nullable|image|max:2048',
            'settings.logo_file' => 'nullable|image|max:2048',
            'settings.strip_file' => 'nullable|image|max:2048',
            'sender_address' => 'sometimes|required|string|unique:users,sender_address,' . auth()->id()
        ];
    }
}
