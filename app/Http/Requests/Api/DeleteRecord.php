<?php

namespace App\Http\Requests\Api;

class DeleteRecord extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $project = $this->route('project');
        $record = $this->route('record');
        if ($record->project_id != $project->id) {
            return false;
        }

        if ($project->user_id == auth()->id()) {
            return true;
        }

        return user()->has_access($project->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }
}
