<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateCoupon extends FormRequest
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
            'currency' => 'required|string',
            'duration' => 'required|string',
            'duration_in_months' => 'nullable|numeric',
            'name' => 'required|string|max:255',
            'percent_off' => 'nullable|numeric',
            'amount_off' => 'nullable|numeric',
            'redeem_by' => 'nullable|integer',
            'max_redemptions' => 'nullable|integer'
        ];
    }
}
