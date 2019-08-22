<?php

namespace App;

use App\Traits\HasAddresses;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Cviebrock\EloquentTaggable\Taggable;
use Nicolaslopezj\Searchable\SearchableTrait;
use Fouladgar\EloquentBuilder\Facade as Filters;

/**
 * @property string id
 */
class Contact extends Model
{
    use Taggable, Traits\UuidModelTrait, SoftDeletes, Traits\HasAddresses, SearchableTrait, HasAddresses;

    public const GENDER_MALE   = 'male';
    public const GENDER_FEMALE = 'female';
    public const GENDER_OTHER  = 'other';

    public const TYPE_COMPANY_EN = 'company';
    public const TYPE_COMPANY_JP = '企業';
    public const TYPE_CONTACT_EN = 'contact';
    public const TYPE_CONTACT_JP = '個人';

    public const STATUS_NEW         = 'new';
    public const STATUS_LEAD        = 'lead';
    public const STATUS_FAN         = 'fan';
    public const STATUS_SUBSCRIBER  = 'subscriber';
    public const STATUS_OPPORTUNITY = 'opportunity';
    public const STATUS_CUSTOMER    = 'customer';
    public const STATUS_OTHER       = 'other';

    protected $guarded = ['id', 'user_id'];

    // TODO: add fillable and check them

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'birthday',
        'last_contact_at',
        'registered_at'
    ];

    protected $casts = [
        'is_company' => 'boolean',
        'is_vendor' => 'boolean',
        'accepts_marketing' => 'boolean',
        'meta' => 'array'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Searchable rules.
     *
     * @var array
     */
    protected $searchable = [
        /**
         * Columns and their priority in search results.
         * Columns with higher values are more important.
         * Columns with equal values have equal importance.
         *
         * @var array
         */
        'columns' => [
            'contacts.first_name' => 10,
            'contacts.last_name' => 10,
            'contacts.name' => 10,
            'contacts.name_katakana' => 10,
            'contacts.email' => 5,
            'contacts.source' => 3,
            'contacts.notes' => 2
            // 'customers.body' => 1,
        ]
        // 'joins' => [
        // 'customers' => ['contact.id','customers.contact_id'],
        // ],
    ];
    /**
     * Get the user that owns the contact.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that assigned to the contact.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the contact company.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Contact::class, 'company_id');
    }

    /**
     * Get the contact parent.
     *
     * @relation('BelongsTo')
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Contact::class, 'parent_id');
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

    /**
     * The customers that contact assigned to.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * The contacts that comany has.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contacts()
    {
        return $this->hasMany(Contact::class, 'company_id');
    }

    /**
     * The payment sources that contact assigned to.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payment_sources()
    {
        return $this->hasMany(PaymentSource::class);
    }

    public function payment_source($remote_id, $type)
    {
        return $this->payment_sources()
            ->where('remote_id', $remote_id)
            ->where('type', $type)
            ->first();
    }

    public function stripe($remote_id)
    {
        return $this->payment_source($remote_id, 'stripe');
    }

    public function GetStripeCustomerAttribute()
    {
        return $this->customers->first();
    }

    public function subscribed($subscription = null)
    {
        $customer = $this->stripe_customer;
        return $customer ? $customer->subscribed($subscription ?: null) : false;
    }

    public function mailchimpLists()
    {
        return $this->belongsToMany(
            MailchimpList::class,
            'mailchimp_list_contacts',
            'contact_id',
            'mailchimp_list_id'
        );
    }

    public static function FindOrCreateFromStripe($user, $payload)
    {
        $source = current(array_get($payload, 'data.object.sources.data', []));
        $source = $source ?: array_filter(array_get($payload, 'data.object.source', []));

        $customer_id = array_get($payload, 'data.object.customer', '');
        $customer_id = $customer_id ?: array_get($payload, 'data.object.id');

        $email = array_get($payload, 'data.object.email');
        $email = $email ?: array_get($payload, 'data.object.receipt_email');

        $name = array_get($source, 'name');

        logger()->debug("findOrCreateFromStripe", [$customer_id, $email, $name]);
        if (!$customer_id) {
            logger()->debug("no customer id", [$name, $email, $source, $payload]);
            return false;
        }

        $contact = false;
        // try to find contact by email or name
        if ($email) {
            $contact = $user
                ->contacts()
                ->where('email', $email)
                ->first();
        } elseif ($name) {
            $contact = $user
                ->contacts()
                ->where('name', $name)
                ->first();
        }

        if (!$contact) {
            logger()->debug("no contact");
            // get contact via payment source
            $ps = PaymentSource::where('remote_id', $customer_id)
                ->where('type', 'stripe')
                ->first();
            if ($ps) {
                logger()->debug("payment source found", [$ps->id]);
                $contact = $ps->contact;
                // if contact doesn't have email, set it
                if (!$contact->email) {
                    $contact->email = $email;
                    $contact->save();
                }
            }
        }

        if (!$contact) {
            logger()->debug("no contact, payment source:", [$ps]);
            // couldn't get contact from payment source, try to create
            DB::connection()
                ->getPdo()
                ->exec('LOCK TABLE `contacts` WRITE');
            try {
                $contact = $user->contacts()->create([
                    'name' => $name,
                    'email' => $email,
                    'source' => 'stripe',
                    'status' => 'customer',
                    'accepts_marketing' => true
                ]);
                logger()->debug('created new contact', [$contact->id]);
            } catch (\Illuminate\Database\QueryException $e) {
                logger()
                    ->logger()
                    ->debug($e->getMessage());
                return self::findOrCreateFromStripe($user, $payload);
            } finally {
                DB::connection()
                    ->getPdo()
                    ->exec('UNLOCK TABLES');
            }
        } else {
            logger()->debug('got contact', [$contact->id]);
            if ($name != $contact->name) {
                $contact->name = $name;
                $contact->save();
            }
            // contact exists, find payment method or use previously found one
            $ps =
                $ps ??
                $contact
                ->payment_sources()
                ->where('remote_id', $customer_id)
                ->where('type', 'stripe')
                ->first();
            if ($ps) {
                // we have record about payment method, check card info
                if (!$ps->card_last_four && $source) {
                    $ps->card_last_four = array_get($source, 'last4');
                    $ps->card_brand = array_get($source, 'brand');
                    $ps->meta = array_merge($ps->meta, [
                        'exp_year' => array_get($source, 'exp_year'),
                        'exp_month' => array_get($source, 'exp_month')
                    ]);
                    $ps->save();
                }
                return $contact;
            }
        }
        logger()->debug("no payment source on contact, creating");
        // no payment method on contact, create one
        $contact->payment_sources()->create([
            'remote_id' => $customer_id,
            'type' => 'stripe',
            'default' => true,
            'card_last_four' => array_get($source, 'last4'),
            'card_brand' => array_get($source, 'brand'),
            'meta' => [
                'exp_year' => array_get($source, 'exp_year'),
                'exp_month' => array_get($source, 'exp_month')
            ]
        ]);

        return $contact;
    }

    public function scopeWithFilters($q, $filters)
    {
        return Filters::to($q, $filters);
    }

    public function getName()
    {
        return $this->name ? $this->name : trim($this->last_name . ' ' . $this->first_name);
    }
}
