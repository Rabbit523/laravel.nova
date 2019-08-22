<?php

namespace App;

use Illuminate\Http\Request;
use App\Metrics\Partition;

class CustomersPerPlan extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function calculate(Request $request)
    {
        $project = $request->route('project');
        if ($project && !is_object($project)) {
            $project = Project::where('id', $project)->firstOrFail();
        }
        $plans = $project->plans;

        $results = Customer::select(
            'plan_id',
            \DB::raw("COUNT(`customers`.`id`) as aggregate")
        )
            ->leftJoin('customer_plans', 'customer_plans.customer_id', '=', 'customers.id')
            ->groupBy('plan_id')
            ->whereRaw(
                '(`customer_plans`.ends_at IS NULL OR customer_plans.ends_at > CURRENT_TIMESTAMP)'
            )
            ->whereIn('plan_id', $plans->pluck('id')->all())
            ->orWhereNull('plan_id')
            ->get();

        return $this->result(
            $results
                ->mapWithKeys(function ($result) {
                    return $this->formatAggregateResult($result, 'plan_id');
                })
                ->all()
        )->label(function ($value) use ($plans) {
            switch ($value) {
                case null:
                    return 'None';
                default:
                    return $value;
            }
        });
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'customers-per-plan';
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return  \DateTimeInterface|\DateInterval|float|int
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }
}
