<?php

namespace App;

use App\Services\Google\Contracts\GoogleContactService;
use App\Services\Freee\FreeeContactsService;
use App\Services\HubSpotContactService;
use Illuminate\Database\Eloquent\Model;
use DrewM\MailChimp\MailChimp;

/**
 * @property User user
 */
class Integration extends Model
{
    use Traits\UuidModelTrait;

    public const SERVICE_GOOGLE  = 'google';
    public const SERVICE_HUBSPOT = 'hubspot';
    public const SERVICE_FREEE   = 'freee';

    protected $guarded = ['id', 'user_id'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['remote_id', 'details', 'service', 'last_status'];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = [];

    //TODO: rename into meta someday...
    protected $casts = [
        'details' => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['last_updated_at'];

    private $updated = 0;
    private $added = 0;

    /**
     * Get the user that owns the integration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get stats.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stats()
    {
        return $this->hasMany(IntegrationStat::class);
    }

    /**
     * Get webhooks.
     *
     * @relation('HasMany')
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function webhooks()
    {
        return $this->hasMany(Webhook::class);
    }

    public function touchLastUpdated()
    {
        $this->last_updated_at = now();
        $this->save();
    }

    public function setLastStatus($status = 'success', $error = false)
    {
        $this->last_updated_at = now();
        $this->last_status = $status;
        if ($error) {
            $details = $this->details;
            $details['error'] = $error;
            $this->details = $details;
        }
        $this->save();
    }

    public function importMailchimpContacts()
    {
        $mailchimp = new MailChimp($this->remote_id);
        $lists = [];
        $limit = 100;
        $listsOffset = 0;
        do {
            $listsRequest = $mailchimp->get("lists?count=$limit&offset=$listsOffset");
            $listsTotal = array_get($listsRequest, 'total_items');
            $lists = array_merge($lists, array_get($listsRequest, 'lists', []));
            $listsOffset += $limit;
        } while (count($lists) < $listsTotal);

        foreach ($lists as $list) {
            $listId = $list['id'];
            $mailchimpList = $this->user->mailchimpLists()->updateOrCreate(
                ['mailchimp_id' => $listId],
                ['name' => $list['name']]
            );
            $members = [];
            $membersOffset = 0;
            do {
                $membersRequest = $mailchimp->get(
                    "lists/$listId/members?count=$limit&offset=$membersOffset"
                );
                $membersTotal = array_get($membersRequest, 'total_items');
                $members = array_merge($members, array_get($membersRequest, 'members', []));
                $membersOffset += $limit;
            } while (count($members) < $membersTotal);

            foreach ($members as $member) {
                $data = [
                    'email' => $member['email_address'],
                    'accepts_marketing' => 1,
                    'source' => 'mailchimp',
                ];
                $address = array_get($member, 'merge_fields.ADDRESS');
                $phone = array_get($member, 'merge_fields.PHONE');
                $contact = $mailchimpList
                    ->contacts()
                    ->where('email', $member['email_address'])
                    ->first();

                if ($address) {
                    $data['meta']['address'] = $address;
                }

                if ($phone) {
                    $data['phone'] = $phone;
                }

                if (array_get($member, 'merge_fields.NAME')) {
                    $data['name'] = array_get($member, 'merge_fields.NAME');
                } else {
                    $data['first_name'] = array_get($member, 'merge_fields.FNAME');
                    $data['last_name'] = array_get($member, 'merge_fields.LNAME');
                }

                if ($contact) {
                    $contact->fill($data);
                    $contact->save();
                } else {
                    $contact = $this->user->contacts()->create($data);
                    $mailchimpList->contacts()->attach($contact);
                }
            }
        }

        $this->setLastStatus();
        return true;
    }

    /**
     * Dispatch job to import contacts.
     *
     * @return Boolean
     */
    public function importFreee()
    {
        try {
            app(FreeeContactsService::class)->fetch($this);
        } catch (\Exception $e) {
            log_error($e);
            $this->setLastStatus('failed', $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Dispatch job to import contacts.
     *
     * @return Boolean
     */
    public function importGoogleContacts(): bool
    {
        try {
            app(GoogleContactService::class)->fetch($this);
        } catch (\Exception $e) {
            log_error($e);
            $this->setLastStatus('failed', $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Dispatch job to import contacts.
     *
     * @return Boolean
     */
    public function importHubSpotContacts(): bool
    {
        try {
            (new HubSpotContactService)->fetch($this);
        } catch (\Exception $e) {
            log_error($e);
            $this->setLastStatus('failed', $e->getMessage());

            return false;
        }

        return true;
    }
}
