<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class IntegrationStat extends Model
{
    protected $guarded = ['integration_id'];

    protected $casts = [
        'meta' => 'array'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * Get the integration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
