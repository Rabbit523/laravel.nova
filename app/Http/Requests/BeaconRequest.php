<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use App\Project;

class BeaconRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $beacon = $this->route('beacon');
        if (!$beacon) {
            if (!request()->has('beacon.project_id')) {
                return false;
            }
            $project = Project::where(
                'id',
                request()->input('beacon.project_id')
            )->firstOrFail();
        } else {
            $project = $beacon->project;
        }
        $this->project = $project;
        if ($project->user_id == auth()->id()) {
            return true;
        }
        return user()->has_access($project->id);
    }

    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    protected function validationData()
    {
        return $this->get('beacon') ?: [];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Request $request)
    {
        $rules = [
            'settings' => 'required',
            'plans' => 'sometimes',
            'is_enabled' => 'required|boolean'
        ];

        switch ($this->getMethod()) {
            case 'POST':
            case 'PUT':
            case 'PATCH':
                // TODO: check project doesn't have a beacon already
                return $rules;
            case 'DELETE':
                return [];
        }
        return [];
    }
}
