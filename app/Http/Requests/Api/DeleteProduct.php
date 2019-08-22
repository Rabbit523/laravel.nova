<?php

namespace App\Http\Requests\Api;

class DeleteProduct extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $product = $this->route('product');

        return $product->user_id == auth()->id();
    }
}
