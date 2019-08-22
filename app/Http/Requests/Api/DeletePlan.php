<?php

namespace App\Http\Requests\Api;

class DeletePlan extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $plan = $this->route('plan');

        return $plan->product->user_id == auth()->id();
    }
}
