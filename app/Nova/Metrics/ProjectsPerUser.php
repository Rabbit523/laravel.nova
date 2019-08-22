<?php

namespace App\Nova\Metrics;

use Illuminate\Http\Request;
use Laravel\Nova\Metrics\Partition;

class ProjectsPerUser extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function calculate(Request $request)
    {
        $results = \DB::select(
            'SELECT `pc` AS `projects`, COUNT(`pc`) AS `aggregate` FROM (SELECT COUNT(`p`.`id`) AS `pc`, `u`.`id` FROM `users` `u` LEFT JOIN `projects` `p` ON `p`.`user_id`=`u`.`id` GROUP BY `u`.`id`) AS pcs GROUP BY `pc`'
        );

        return $this->result(
            collect($results)
                ->mapWithKeys(function ($result) {
                    return $this->formatAggregateResult($result, 'projects');
                })
                ->all()
        );
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return  \DateTimeInterface|\DateInterval|float|int
     */
    public function cacheFor()
    {
        return now()->addMinutes(5);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'projects-per-user';
    }
}
