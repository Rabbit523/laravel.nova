<?php
namespace App;

use Laravel\Cashier\Subscription;

class ProjectSubscription extends Subscription
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'subscriptions';

    public function owner()
    {
        return $this->belongsTo(Project::class, 'user_id');
    }
}
