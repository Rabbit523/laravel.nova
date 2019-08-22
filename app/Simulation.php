<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\UuidModelTrait;

class Simulation extends Model
{
    use Traits\UuidModelTrait;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'growth',
        'growth_rate',
        'market_size',
        'market_share',
        'apc',
        'mi',
        'conversion_rate',
        'avp',
        'initial_cost',
        'launch_period',
        'cogs',
        'cpmi'
    ];

    // cogs - in percent from average price
    // cost_per_acquisition -  rename to Cost per MI (CPMI) (variable_marketing_cost)
    // fixed_cost - cogs
    // variable_production_cost - remove

    /**
     * Get the project that owns the model.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
