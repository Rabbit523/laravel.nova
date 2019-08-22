<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use App\UuidModelTrait;
use Laravel\Cashier\Billable;
use Cake\Chronos\Chronos;

class Project extends Model
{
    use Traits\UuidModelTrait, Billable, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'business_model',
        'url',
        'currency',
        'start_date',
        'duration',
        'description',
        'with_model',
        'with_launch',
        'with_cost_manager',
        'stripe_id',
        'card_brand',
        'card_last_four',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'start_date'];

    protected $casts = [
        'start_date' => 'date:Y-m',
        'with_model' => 'boolean',
        'with_launch' => 'boolean',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = []; //'simulation']; //'user',

    /**
     * Get the user that owns the project.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get project simulation model.
     *
     * @relation('HasOne')
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function simulation()
    {
        return $this->hasOne(Simulation::class);
    }

    /**
     * Get project beacon model.
     *
     * @relation('HasOne')
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function beacon()
    {
        return $this->hasOne(Beacon::class);
    }

    /**
     * Get project records.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function records()
    {
        return $this->hasMany(Record::class);
    }

    /**
     * Get all project transactions.
     */
    public function transactions()
    {
        return $this->hasManyThrough(Transaction::class, Record::class);
    }

    /**
     * Get project revenue transactions.
     */
    public function revenue_transactions()
    {
        return $this->transactions()->where('records.type', 'revenue');
    }

    public function recordsActual($type)
    {
        return $this->recordsPlanned($type, false);
    }

    public function recordsPlanned($type, $planned = true)
    {
        return $this->records()
            ->where('type', $type)
            ->where('planned', $planned)
            ->with(['product', 'contact', 'plan', 'project'])
            ->with([
                'monthly' => function ($query) {
                    $query
                        ->select('record_id', 'date', 'price', 'quantity', 'meta')
                        ->orderBy('date', 'asc');
                },
            ])
            ->get();
    }

    public function recordsDailyActual($type, $date)
    {
        return $this->recordsDailyPlanned($type, $date, false);
    }

    public function recordsDailyPlanned($type, $date, $planned = true)
    {
        $date = date_parse($date);
        $from = Chronos::create($date['year'], $date['month'], 1, 0, 0, 0, 'UTC');
        $to = Chronos::create($date['year'], $date['month'], 1, 0, 0, 0, 'UTC');
        $to = $to->addMonth()->subDay();
        //forcing index to 'date' to speed up query 4x-5x times
        return $this->records()
            ->where('type', $type)
            ->where('planned', $planned)
            ->with(['product', 'contact', 'plan', 'project'])
            ->with([
                'daily' => function ($query) use ($from, $to) {
                    $query
                        ->from(\DB::raw('`daily_records` FORCE INDEX (date)'))
                        ->select('record_id', 'date', 'price', 'quantity', 'meta')
                        ->whereBetween('date', [$from, $to])
                        ->orderBy('date', 'asc');
                },
            ])
            ->get();
    }

    /**
     * Get the key name for route model binding.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * The teams that belong to the project.
     * @relation('BelongsToMany')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class);
    }

    /**
     * The products that belong to the project.
     * @relation('BelongsToMany')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Get all of the plans for the product.
     */
    public function getPlansAttribute()
    {
        return Plan::leftJoin(
            'product_project',
            'product_project.product_id',
            '=',
            'plans.product_id'
        )
            ->where('product_project.project_id', $this->id)
            ->get();
    }

    /**
     * The members that have access to the project.
     */
    public function getTeamMembersAttribute()
    {
        return User::leftJoin('team_user', 'team_user.user_id', '=', 'users.id')
            ->leftJoin('project_team', 'team_user.team_id', '=', 'project_team.team_id')
            ->where('project_id', $this->id)
            ->get();
    }

    /**
     * Get project datasources.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function datasources()
    {
        return $this->hasMany(DataSource::class);
    }

    public function csvs()
    {
        return $this->hasMany(DataSource::class)->where('type', 'csv');
    }

    /**
     * Get project customers.
     *
     * @relation('BelongsToMany')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function customers()
    {
        return $this->belongsToMany(Customer::class);
    }

    public function taxPercentage()
    {
        return 8;
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(ProjectSubscription::class, 'user_id')->orderBy(
            'created_at',
            'desc'
        );
    }

    public function addSubscription($plan_id, $coupon, $token)
    {
        if ($token) {
            $this->updateCard($token);
        }
        if (!$plan_id) {
            return;
        }

        if (!$this->user->card_last_four) {
            $this->user->card_last_four = $this->card_last_four;
            $this->user->card_brand = $this->card_brand;
            $this->user->save();
        }

        $subscription = $this->newSubscription('default', $plan_id)->withMetadata([
            'project_id' => $this->id,
        ]);

        if (!empty($coupon)) {
            $subscription->withCoupon($coupon);
        }

        // don't count this project
        if ($this->user->hasTrialCapacity(false)) {
            $subscription->trialDays(30);
        } else {
            $subscription->skipTrial();
        }

        $subscription->create($token ?? null);
    }

    public function cancelSubscription($now = false)
    {
        if (!$this->stripe_id) {
            return;
        }
        if (!$this->subscription('default')) {
            return;
        }
        if ($now) {
            return $this->subscription('default')->cancelNow();
        }
        return $this->subscription('default')->cancel();
    }

    public function resumeSubscription()
    {
        if (!$this->subscription('default')) {
            return;
        }
        $this->subscription('default')->resume();
    }
}
