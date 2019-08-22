<?php

namespace App;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;

use Laravel\Cashier\Billable;
use App\Notifications\AppNotification;

use App\Notifications\ResetPassword as ResetPasswordNotification;

class User extends Authenticatable implements HasLocalePreference
{
    use Billable, Notifiable, Traits\UuidModelTrait, CanResetPassword, SoftDeletes, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'language',
        'password',
        'on_mail_list',
        'last_visit_at',
        'settings',
        'sender_address',
        'connect_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token', 'is_team'];

    protected $casts = [
        'on_mail_list' => 'boolean',
        'is_team' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['trial_ends_at', 'last_visit_at'];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = [];

    /**
     * Get all the projects by the user.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function projects()
    {
        return $this->hasMany(Project::class)->latest();
    }

    /**
     * The products that belong to the user.
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The datasources that belong to the user.
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function datasources()
    {
        return $this->hasMany(DataSource::class);
    }

    /**
     * Sold products.
     */
    public function products_sold()
    {
        return $this->products()
            ->with(['plans:id,name,product_id,amount,currency', 'projects'])
            ->where('sold', true);
    }

    /**
     * Get all of the projects for the user.
     */
    public function team_projects()
    {
        return Project::where('team_user.user_id', $this->id)
            ->leftJoin('project_team', 'project_team.project_id', '=', 'projects.id')
            ->leftJoin('team_user', 'team_user.team_id', '=', 'project_team.team_id');
    }

    public function has_access($project_id)
    {
        return DB::table('project_team')
            ->leftJoin('team_user', 'team_user.team_id', '=', 'project_team.team_id')
            ->where('project_id', $project_id)
            ->where('team_user.user_id', $this->id)
            ->exists();
    }

    /**
     * Get all user integrations.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function integrations()
    {
        return $this->hasMany(Integration::class);
    }

    /**
     * The teams that were created by the user.
     */
    public function teams()
    {
        return $this->hasMany(Team::class)->latest();
    }

    /**
     * The contacts that were createad by user.
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * The company details.
     * @relation('HasOne')
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function company()
    {
        return $this->hasOne(Company::class);
    }

    /**
     * The Connect verification details.
     * @relation('HasOne')
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function verification_status()
    {
        return $this->hasOne(UserVerification::class)->withDefault();
    }

    /**
     * The teams that user has joined.
     */
    public function joined_teams()
    {
        return $this->belongsToMany(Team::class);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function preferredLocale()
    {
        return $this->language;
    }

    public function scopeEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    public function taxPercentage()
    {
        return 8;
    }

    public function trialEnded()
    {
        return $this->trial_ends_at ? $this->trial_ends_at->isPast() : false;
    }

    public function hasTrialCapacity($start_from_zero = true)
    {
        return (!$this->trialEnded() && ($start_from_zero
            ? $this->projects()->count() === 0
            : $this->projects()->count() <= 1));
    }

    /**
     * Determine if the Stripe model has subscribed before.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
     * @return bool
     */
    public function hasEverSubscribedTo($subscription = 'default', $plan = null)
    {
        if (is_null($subscription = $this->subscription($subscription))) {
            return false;
        }

        return $plan ? $subscription->provider_plan == $plan : true;
    }

    /**
     * Get the entity's notifications.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function notifications()
    {
        return $this->hasMany(AppNotification::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the entity's read notifications.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function readNotifications()
    {
        return $this->notifications()->whereNotNull('read_at');
    }

    /**
     * Get the entity's unread notifications.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    /**
     * Get mailchimp lists.
     */
    public function mailchimpLists()
    {
        return $this->hasMany(MailchimpList::class, 'user_id', 'id');
    }

    public function firstOrCreateProduct($search, $data)
    {
        return $this->products()
            ->lockForUpdate()
            ->firstOrCreate($search, $data);
    }
}
