<?php
namespace App;

use Laravel\Cashier\Subscription;

class CustomerSubscription extends Subscription
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'subscriptions';

    public static function boot()
    {
        parent::boot();

        static::created(function ($s) {
            if ($s->name == 'default') {
                return;
            }
            logger()->debug('created customer subscription', [
                'customer_id' => $s->user_id,
                'plan_id' => $s->stripe_plan,
                'created_at' => $s->created_at,
                'ends_at' => $s->ends_at,
                'project' => $s->project->id,
                'user' => $s->project->user_id
            ]);

            $plan = $s->project->plans->firstWhere('payment_id', $s->stripe_plan);
            if (!$plan) {
                logger()->error('no plan in subscription create');
                return;
            }

            CustomerPlan::create([
                'customer_id' => $s->user_id,
                'plan_id' => $plan->id,
                'created_at' => $s->created_at,
                'ends_at' => $s->ends_at
            ]);
        });

        static::updating(function ($s) {
            if ($s->name == 'default') {
                return;
            }
            $original = $s->getOriginal();
            logger()->debug('updating customer subscription', [
                'customer_id' => $s->user_id,
                'plan' => $s->stripe_plan,
                'created_at' => $s->created_at,
                'ends_at' => $s->ends_at,
                'original_ends_at' => $original['ends_at'],
                'project' => $s->project->id,
                'user' => $s->project->user_id
            ]);

            $plan = $s->project->plans->firstWhere('payment_id', $s->stripe_plan);
            if (!$plan) {
                logger()->error('no plan in subscription create');
                return;
            }
            if ($s->ends_at == $original['ends_at']) {
                return;
            }
            logger()->debug('updating customer plan', [$plan->id]);

            $data = [
                'customer_id' => $s->user_id,
                'plan_id' => $plan->id,
                'created_at' => $s->created_at,
                'ends_at' => $s->ends_at
            ];
            CustomerPlan::updateOrCreate(array_except($data, ['ends_at']), $data);
        });
    }

    public function owner()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'name');
    }
}
