<?php

namespace App\Nova\Metrics;

use App\User;
use Laravel\Cashier;
use Illuminate\Http\Request;
use Laravel\Nova\Metrics\Partition;

class UsersPerPlan extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function calculate(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        $plans = \Stripe\Plan::all(["limit" => 6]);

        $plans = collect($plans->data);
        // ->map(function ($plan) {
        //     $product = \Stripe\Product::retrieve($plan->product);
        //     $plan->name = ucFirst($product->name);
        //     return $plan;
        // });

        $results = User::select('stripe_plan', \DB::raw("COUNT(`users`.`id`) as aggregate"))
            ->leftJoin('subscriptions', 'subscriptions.user_id', '=', 'users.id')
            ->orderBy('subscriptions.created_at', 'desc')
            ->groupBy('subscriptions.stripe_plan')
            ->get();

        return $this->result(
            $results
                ->mapWithKeys(function ($result) {
                    return $this->formatAggregateResult($result, 'stripe_plan');
                })
                ->all()
        )->label(function ($value) use ($plans) {
            switch ($value) {
                case null:
                    return 'None';
                default:
                    return ($plan = $plans->firstWhere('id', $value))
                        ? $plan->nickname
                        : 'unknown';
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
        return 'users-by-plan';
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
