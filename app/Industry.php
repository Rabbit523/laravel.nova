<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    protected $guarded = ['id'];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['parent'];

    /**
     * Get the user that owns the phone.
     */
    public function companies()
    {
        return $this->hasMany(Company::class, 'industry_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo('App\Industry', 'parent_id');
    }
}
