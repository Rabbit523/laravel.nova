<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ShowInvoices extends FormRequest
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
            'filters.limit' => 'nullable|numeric|max:100',
            'filters.customer' => 'nullable|string',
            'filters.created.gt' => 'nullable|integer',
            'filters.created.gte' => 'nullable|integer',
            'filters.created.lt' => 'nullable|integer',
            'filters.created.lte' => 'nullable|integer',
            'filters.due_date.gt' => 'nullable|integer',
            'filters.due_date.gte' => 'nullable|integer',
            'filters.due_date.lt' => 'nullable|integer',
            'filters.due_date.lte' => 'nullable|integer',
            'filters.subscription' => 'nullable|string'
        ];
    }
}
