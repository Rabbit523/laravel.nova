<?php

namespace App\Http\Requests\Api;

class DeleteTeam extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $team = $this->route('team');

        return $team->user_id == auth()->id();
    }
}
