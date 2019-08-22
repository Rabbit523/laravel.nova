<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beacon extends Model
{
    use Traits\UuidModelTrait, SoftDeletes;

    protected $guarded = ['id', 'project_id'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'plans' => 'array',
        'settings' => 'array'
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['project'];

    protected $hidden = ['created_at', 'deleted_at'];

    /**
     * Get the project that owns the data source.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
