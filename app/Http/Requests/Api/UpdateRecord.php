<?php

namespace App\Http\Requests\Api;

class UpdateRecord extends ApiRequest
{
    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData()
    {
        return $this->get('record') ?: [];
    }

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
        return [
            'name' => 'required|string|max:255',
            'autofill' => 'sometimes'
        ];
    }
}
