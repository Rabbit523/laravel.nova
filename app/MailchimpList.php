<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\UuidModelTrait;

class MailchimpList extends Model
{
    use Traits\UuidModelTrait;

    protected $guarded = ['id', 'user_id'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * Get the user that owns the customer.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contacts()
    {
        return $this->belongsToMany(
            Contact::class,
            'mailchimp_list_contacts',
            'mailchimp_list_id',
            'contact_id'
        );
    }
}
