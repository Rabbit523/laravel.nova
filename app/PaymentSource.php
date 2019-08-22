<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\UuidModelTrait;

class PaymentSource extends Model
{
    use Traits\UuidModelTrait;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'remote_id',
        'type',
        'card_last_four',
        'card_brand',
        'default',
        'meta'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'meta' => 'array'
    ];

    /**
     * Get the contact that owns the payment source.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
