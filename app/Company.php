<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $guarded = ['user_id'];

    protected $fillable = [
        'name',
        'name_kana',
        'industry_code',
        'size',
        'country',
        'zip',
        'state',
        'city',
        'town',
        'address',
        'other',
        'phone',
        'meta',
        'industry_id',
        'fiscal_start',
        'fiscal_end',
        'name_kanji',
        'tax_id',
        'business_type'
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['industry'];

    protected $casts = [
        'meta' => 'array'
    ];

    /**
     * Get the user that owns the company.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get industry.
     *
     * @relation('HasOne')
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function industry()
    {
        return $this->hasOne(Industry::class, 'id', 'industry_id');
    }
}
