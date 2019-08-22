<?php

namespace App\Http\Requests\Api;

class DeleteProject extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $project = $this->route('project');

        return $project->user_id == auth()->id();
    }
}
