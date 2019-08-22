<?php

namespace App\Http\Requests\Api;

class UpdateProject extends ApiRequest
{
    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData()
    {
        return $this->get('project') ?: [];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $project = $this->route('project');

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
            'title' => 'sometimes|string|max:255',
            'url' => 'nullable|string|max:255',
            'currency' => 'sometimes|string',
            'start_date' => 'required|date'
        ];
    }
}
