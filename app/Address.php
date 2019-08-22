<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends Model
{
    use Traits\UuidModelTrait;

    /** @var array */
    protected $guarded = ['id', 'model_id', 'model_type', 'created_at', 'updated_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'role',
        'street',
        'other',
        'city',
        'state',
        'country',
        'postcode',
        'phone'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Change the role of the current address model.
     *
     * @param string $role
     *
     * @return bool
     */
    public function role(string $role): bool
    {
        return $this->update(compact('role'));
    }
}
