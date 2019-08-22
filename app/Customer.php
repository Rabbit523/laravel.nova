<?php
namespace App;

use App\Traits\ConnectBillable as Billable;
use App\Services\Stripe\StripeInvoicesService;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Notifications\Notifiable;
// use App\Notifications\ResetPassword as ResetPasswordNotification;

class Customer extends Authenticatable
{
    use Billable, Notifiable, Traits\UuidModelTrait, CanResetPassword;

    protected $guarded = ['id', 'contact_id'];

    protected $guard = "customers";

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'trial_ends_at', 'last_visit_at'];

    protected $casts = [
        'meta' => 'array'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token'
    ];

    /**
     * Get the contact that owns the customer.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }


    /**
     * Get customer plans
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customer_plan()
    {
        return $this->hasMany(CustomerPlan::class);
    }

    /**
     * Get connect user that this customer registered with.
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        // Access belongsTo via Contact
        return $this->contact->belongsTo(User::class);
    }

    /**
     * Get the tax percentage to apply to the subscription.
     *
     * @return int|float
     */
    public function taxPercentage()
    {
        return 8;
    }

    /**
     * Get the application Fee percentage.
     *
     * @return int|float
     */
    public function applicationFeePercent()
    {
        // TODO: hardcoded for now, should be bound to plan or otherwise controlled
        return config('services.stripe.application_fee', 0);
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(CustomerSubscription::class, 'user_id')->orderBy(
            'created_at',
            'desc'
        );
    }

    public function getInvoicesAttribute()
    {
        $invoicesService = new StripeInvoicesService();
        $invoices = $invoicesService->setUser($this->user)->getAllInvoices([
            "limit" => 3, "customer" => $this->stripe_id
        ]);
        return $invoices->data;
    }

    // /**
    //  * Send the password reset notification.
    //  *
    //  * @param  string  $token
    //  * @return void
    //  */
    // public function sendPasswordResetNotification($token)
    // {
    //     $this->notify(new ResetPasswordNotification($token));
    // }
}
