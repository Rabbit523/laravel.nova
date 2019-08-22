<?php

namespace App\Http\Requests\Api;

class InviteTeamMember extends ApiRequest
{
    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData()
    {
        return $this->get('member') ?: [];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $team = $this->route('team');
        // check if owner team
        // if ($team->project_id != $project->id) {
        //     return false;
        // }
        return $team->user_id == auth()->id() || $team->members->contains(auth()->id());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'required|string|max:255'
        ];
    }
}
