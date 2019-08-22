<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Jobs\ProcessDatasource;
use App\Services\Stripe\StripeConnectService;

class Webhook extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['type', 'service', 'owner_id', 'payload'];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get the user that owns the webhook.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the integration that owns the webhook.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    public function parse(Integration $integration = null)
    {
        $payload = $this->payload;
        if ($this->service == 'connect') {
            $connect_service = new StripeConnectService($this->id);
            $method = 'handle' . studly_case(str_replace('.', '_', $payload['type']));
            if (method_exists($connect_service, $method)) {
                return $connect_service->$method($payload);
            }
            return true;
        }

        $integration = $integration ?: $this->integration;
        $project = false;

        if (array_has($integration, 'details.project')) {
            $project = Project::where(
                'id',
                array_get($integration, 'details.project')
            )->first();
        }

        switch ($payload['type']) {
            case 'customer.created':
            case 'customer.updated':
                // customer.updated might have source, source might have name
                logger()->debug("got customer webhook", $payload);
                if (!array_has($payload, 'data.object.email')) {
                    info("got customer without email");
                    $integration->setLastStatus('failed', 'got customer without email');
                    return true;
                }

                Contact::FindOrCreateFromStripe($integration->user, $payload);

                $integration->setLastStatus('success');
                return true;
            case 'customer.subscription.created':
                // TODO: save contact event here
                $integration->setLastStatus('success');
                return true;
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $ps = PaymentSource::where(
                    'remote_id',
                    array_get($payload, 'data.object.customer')
                )
                    ->where('type', 'stripe')
                    ->first();
                // TODO: save contact event here
                if ($ps) {
                    logger()->debug($payload['type'], [$ps->id]);
                    // $ps->meta = array_merge($ps->meta, [
                    //     'subscription_status' => array_get($payload, 'data.object.status')
                    // ]);
                    // $ps->save();
                }

                $integration->setLastStatus('success');
                return true;
            case 'charge.succeeded':
            case 'invoice.payment_succeeded':
            case 'charge.refunded':
                // TODO: handled failed webhooks
                $integration->setLastStatus('success');
                break;
            default:
                return true;
        }
        // TODO: get project from product
        // what if there is no project assigned to product?

        $datasource = $integration->user->datasources()->create([
            'integration_id' => $integration->id,
            'project_id' => $project ? $project->id : null,
            'webhook_id' => $this->id,
            'name' => $payload['type'],
            'hash' => sha1(json_encode($payload)),
            'type' => 'stripe',
            'record' => [
                'record_type' => 'revenue',
                'planned' => 0,
            ],
        ]);
        logger()->debug('new datasource', ['stripe', $datasource->id, $payload['type']]);

        if (!\App::environment('local')) {
            ProcessDatasource::dispatch($datasource);
        } else {
            $datasource->parse();
        }
        return true;
    }
}
