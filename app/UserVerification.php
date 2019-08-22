<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserVerification extends Model
{
    public $incrementing = false;

    protected $guarded = ['user_id'];

    protected $casts = [
        'currently_due' => 'array',
        'past_due' => 'array',
        'eventually_due' => 'array',
        'charges_enabled' => 'boolean',
        'details_submitted' => 'boolean',
        'payouts_enabled' => 'boolean',
        'current_deadline' => 'datetime:U',
    ];

    /**
     * Get the user that owns the verification details.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the last webhook that updated details.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function webhook()
    {
        return $this->belongsTo(Webhook::class, 'last_webhook_id');
    }
}
