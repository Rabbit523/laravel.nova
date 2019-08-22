<?php

namespace App\Http\Requests\Api;

use App\Metrics\Metric;

class MetricRequest extends ApiRequest
{
    /**
     * Get the cards that should be displayed on the Nova dashboard.
     *
     * @return array
     */
    protected function cards()
    {
        return [
            // new \App\Nova\Metrics\NewUsers(),
            // new \App\Nova\Metrics\UsersPerDay(),
            // new \App\Nova\Metrics\ActiveUsersPerDay(),
            new \App\CustomersPerPlan()
            // new \App\Nova\Metrics\ProjectsPerUser(),
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $project = $this->route('project');
        if ($project && !is_object($project)) {
            $project = \App\Project::where('id', $project)->firstOrFail();
        }
        if ($project->user_id == auth()->id()) {
            return true;
        }
        return user()->has_access($project->id);
    }

    /**
     * Get the metric instance for the given request.
     *
     * @return \App\Metrics\Metric
     */
    public function metric()
    {
        return $this->availableMetrics()->first(function ($metric) {
            return $this->metric === $metric->uriKey();
        })
            ?: abort(404);
    }

    /**
     * Get all of the possible metrics for the request.
     *
     * @return \Illuminate\Support\Collection
     */
    public function availableMetrics()
    {
        return collect($this->cards())->values(); //filter->authorize($this)->
        // return App::availableDashboardCards($this)->whereInstanceOf(Metric::class);
    }
}
