<?php
namespace App\Services\Stripe;

use App\Mail\CustomerInvoice;
use Illuminate\Support\Carbon;
use App\CustomerSubscription as Subscription;
use App\User;
use App\Customer;
use App\Product;
use Laravel\Cashier\Invoice;
use Illuminate\Support\Facades\Mail;

class StripeConnectService extends BaseStripeService
{
    protected $webhook_id;

    /**
     * StripeConnectService constructor
     */
    public function __construct($webhook_id)
    {
        $this->webhook_id = $webhook_id;
        parent::__construct();
    }

    /**
     * Get the billable entity instance by Stripe ID.
     *
     * @param  string  $stripeId
     * @return \Laravel\Cashier\Billable
     */
    protected function getCustomerByStripeId($stripeId)
    {
        return Customer::where('stripe_id', $stripeId)->first();
    }

    /**
     * Get the user instance by Stripe Connect Account ID.
     *
     * @param  string  $stripeId
     * @return \App\User
     */
    protected function getUserByConnectId($connectId)
    {
        return User::where('connect_id', $connectId)->first();
    }

    /**
     * Handle customer subscription updated.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        $user = $this->getCustomerByStripeId($payload['data']['object']['customer']);

        if ($user) {
            $data = $payload['data']['object'];

            $user->subscriptions->filter(function (Subscription $subscription) use ($data) {
                return $subscription->stripe_id === $data['id'];
            })->each(function (Subscription $subscription) use ($data) {
                // Quantity...
                if (isset($data['quantity'])) {
                    $subscription->quantity = $data['quantity'];
                }

                // Plan...
                if (isset($data['plan']['id'])) {
                    $subscription->stripe_plan = $data['plan']['id'];
                }

                // Trial ending date...
                if (isset($data['trial_end'])) {
                    $trial_ends = Carbon::createFromTimestamp($data['trial_end']);

                    if (
                        !$subscription->trial_ends_at ||
                        $subscription->trial_ends_at->ne($trial_ends)
                    ) {
                        $subscription->trial_ends_at = $trial_ends;
                    }
                }

                // Cancellation date...
                if (isset($data['cancel_at_period_end']) && $data['cancel_at_period_end']) {
                    $subscription->ends_at = $subscription->onTrial()
                        ? $subscription->trial_ends_at
                        : Carbon::createFromTimestamp($data['current_period_end']);
                }

                $subscription->save();
            });
        }

        return true;
    }

    /**
     * Handle a cancelled customer from a Stripe subscription.
     *
     * @param  array  $payload
     * @return Boolean
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $user = $this->getCustomerByStripeId($payload['data']['object']['customer']);

        if ($user) {
            $user->subscriptions->filter(function ($subscription) use ($payload) {
                return $subscription->stripe_id === $payload['data']['object']['id'];
            })->each(function ($subscription) {
                $subscription->markAsCancelled();
            });
        }

        return true;
    }

    /**
     * Handle customer updated.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handleCustomerUpdated(array $payload)
    {
        if ($user = $this->getCustomerByStripeId($payload['data']['object']['id'])) {
            $user->updateCardFromStripe();
        }

        return true;
    }

    /**
     * Handle customer source deleted.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handleCustomerSourceDeleted(array $payload)
    {
        if ($user = $this->getCustomerByStripeId($payload['data']['object']['customer'])) {
            $user->updateCardFromStripe();
        }
        // TODO: update our payment sources table
        return true;
    }

    /**
     * Handle deleted customer.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handleCustomerDeleted(array $payload)
    {
        $user = $this->getCustomerByStripeId($payload['data']['object']['id']);

        if ($user) {
            $user->subscriptions->each(function (Subscription $subscription) {
                $subscription->skipTrial()->markAsCancelled();
            });

            $user
                ->forceFill([
                    'stripe_id' => null,
                    'trial_ends_at' => null,
                    'card_brand' => null,
                    'card_last_four' => null,
                ])
                ->save();
        }

        return true;
    }

    /**
     * Handle product created.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handleProductCreated(array $payload)
    {
        $account_id = array_get($payload, 'account');
        info('handleProductCreated', [$account_id]);
        $user = $this->getUserByConnectId($account_id);
        if (!$user) {
            info('handleProductCreated', ['user not found']);
            return false;
        }
        $this->setUser($user);
        try {
            $result = \Stripe\Product::retrieve(
                [
                    'id' => array_get($payload, 'data.object.id'),
                ],
                $this->getOptionsForStripeCall()
            );
        } catch (\Exception $e) {
            log_error($e);
            return false;
        }
        $product = $user->firstOrCreateProduct(
            [
                'payment_id' => $result->id,
                'payment_type' => 'stripe',
            ],
            [
                'sold' => true,
                'managed' => true,
                'payment_id' => $result->id,
                'payment_type' => 'stripe',
                'name' => array_get($result, 'product.name'),
                'slug' => str_slug_u(array_get($result, 'product.name')),
                'description' => array_get($result, 'product.description'),
            ]
        );
        if (!$product) {
            info('Product creation failed.');
            return false;
        }

        return true;
    }

    /**
     * Handle plan created.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handlePlanCreated(array $payload)
    {
        $account_id = array_get($payload, 'account');
        info('handlePlanCreated', [$account_id, array_get($payload, 'data.object.id')]);
        $user = $this->getUserByConnectId($account_id);
        if (!$user) {
            info('handlePlanCreated', ['user not found']);
            return false;
        }
        $this->setUser($user);

        try {
            $result = \Stripe\Plan::retrieve(
                [
                    'id' => array_get($payload, 'data.object.id'),
                    'expand' => ['product'],
                ],
                $this->getOptionsForStripeCall()
            );
        } catch (\Exception $e) {
            log_error($e);
            return false;
        }
        $product = $user->firstOrCreateProduct(
            [
                'payment_id' => $result->product->id,
                'payment_type' => 'stripe',
            ],
            [
                'sold' => true,
                'managed' => true,
                'payment_id' => $result->product->id,
                'payment_type' => 'stripe',
                'name' => array_get($result, 'product.name'),
                'slug' => str_slug_u(array_get($result, 'product.name')),
                'description' => array_get($result, 'product.description'),
            ]
        );
        if (!$product) {
            info('Product creation failed.');
            return false;
        }
        $data = array_only($result->__toArray(), [
            'interval',
            'currency',
            'amount',
            'interval_count',
            'trial_days',
            'billing_day',
            'billing_scheme',
            'description',
        ]);
        $data['name'] = $result->nickname;
        $data['payment_id'] = $result->id;
        $data['payment_type'] = 'stripe';
        $data['managed'] = true;
        $data['tax_included'] = false;
        // $data['slug'] = str_slug_u(array_get($data, 'name'));

        $plan = $product
            ->plans()
            ->lockForUpdate()
            ->firstOrCreate($data);

        if (!$plan) {
            info('Plan creation failed.');
            return false;
        }

        return true;
    }

    protected function extractVerificationStatus(array $payload)
    {
        $account_id = array_get($payload, 'account');
        $type = array_get($payload, 'data.object.object');
        $user = $this->getUserByConnectId($account_id);
        if (!$user) {
            info('extractVerificationStatus', ['user not found']);
            return false;
        }
        info('extractVerificationStatus', [$user->id, $account_id, $type]);
        $requirements = array_get($payload, 'data.object.requirements', []);
        $verification = array_get($payload, 'data.object.verification', []);
        $data = [
            'id' => $account_id,
            'last_webhook_id' => $this->webhook_id,
        ];
        if ($type == 'person') {
            $data['verification_details_code'] = array_get($verification, 'details_code');
            $data['verification_status'] = array_get($verification, 'status');
        } else {
            $data['charges_enabled'] = array_get($payload, 'data.object.charges_enabled');
            $data['payouts_enabled'] = array_get($payload, 'data.object.payouts_enabled');
            $data['details_submitted'] = array_get($payload, 'data.object.details_submitted');
            $data = array_merge($data, $requirements);
        }

        $verification = $user
            ->verification_status()
            ->lockForUpdate()
            ->updateOrCreate(array_only($data, ['id']), $data);
        return true;
    }

    /**
     * Handle account updated.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handleAccountUpdated(array $payload)
    {
        info('handleAccountUpdated', [array_get($payload, 'account')]);
        return $this->extractVerificationStatus($payload);
    }

    /**
     * Handle person updated.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handlePersonUpdated(array $payload)
    {
        info('handlePersonUpdated', [array_get($payload, 'account')]);
        return $this->extractVerificationStatus($payload);
    }

    /**
     * Handle success charge
     *
     * @param array $payload
     */
    public function handleChargeSucceeded(array $payload)
    {
        $account_id = array_get($payload, 'account');
        info('handleChargeSucceeded', [$account_id]);
        $invoicesService = new StripeInvoicesService();

        # Check if user exist
        $user = $this->getUserByConnectId($account_id);
        if (!$user) {
            info('handleChargeSucceeded', ['user not found', $account_id]);
            return false;
        }
        $invoicesService->setUser($user);

        # Check if customer exist
        $customer = $this->getCustomerByStripeId(array_get($payload, 'data.object.customer'));
        if (!$customer) {
            info('Unknown customer in charge succeeded', [
                $payload['data']['object']['customer'],
            ]);
            return false;
        }

        # Check if invoice exist
        try {
            $invoice = $invoicesService->getInvoice(array_get($payload, 'data.object.invoice'));
        } catch (\Exception $e) {
            log_error($e);
            return false;
        }

        if (!$invoice) {
            info('invoice not found');
            return false;
        }

        info('Got invoice', [$invoice->id]);

        # Check if product exist
        $product = $user->products_sold()->where(
            'payment_id',
            array_get($invoice, 'subscription.plan.product')
        )->first();

        if (!$product) {
            debug('Product not found.', [
                array_get($invoice, 'subscription.plan.product'),
            ]);
            return false;
        }

        // TODO: check for project

        # Send email to customer
        try {
            $invoice_object = new Invoice($user, $invoice);

            Mail::to($customer->email)->send(new CustomerInvoice($invoice_object, $user, $product));
            return true;
        } catch (\Exception $e) {
            log_error($e);
            return false;
        }
    }

    /**
     * Handle invoice finalized.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handleInvoiceFinalized(array $payload)
    {
        $account_id = array_get($payload, 'account');
        info('handleInvoiceFinalized', [$account_id]);
        $invoicesService = new StripeInvoicesService();

        $user = $this->getUserByConnectId($account_id);
        if (!$user) {
            info('handleInvoiceFinalized', ['user not found', $account_id]);
            return false;
        }
        $invoicesService->setUser($user);

        $customer = $this->getCustomerByStripeId(array_get($payload, 'data.object.customer'));
        if (!$customer) {
            info('unknown customer in invoice finalized', [
                $payload['data']['object']['customer'],
            ]);
            return false;
        }

        try {
            $invoice = $invoicesService->getInvoice(array_get($payload, 'data.object.id'));
        } catch (\Exception $e) {
            log_error($e);
            return false;
        }

        if (!$invoice) {
            info('invoice not found');
            return false;
        }

        $product = $user->products_sold()->where(
            'payment_id',
            array_get($invoice, 'subscription.plan.product')
        )->first();

        if (!$product) {
            logger()->debug('Product not found.', [
                array_get($invoice, 'subscription.plan.product'),
            ]);
            return false;
        }
        // FIXME: product can be associated with multiple projects
        $project = $product->projects()->first();
        if (!$project) {
            logger()->error('Got invoice for product without project', [
                array_get($payload, 'data.object.id'),
            ]);
            return false;
        }

        $data = [
            'project_id' => isset($project) ? $project->id : null,
            'webhook_id' => $this->webhook_id,
            'name' => $payload['type'],
            'hash' => sha1(json_encode($payload)),
            'type' => 'connect',
            'record' => [
                'record_type' => 'revenue',
                'planned' => 0,
            ],
            'meta' => [
                'product_id' => $product->id,
                'customer_id' => $customer->id,
                'stripe_plan' => array_get($invoice, 'subscription.plan.id'),
            ],
        ];
        $datasource = $user->datasources()->updateOrCreate(array_only($data, ['hash']), $data);

        logger()->debug('new datasource', ['connect', $datasource->id, $payload['type']]);
        return $datasource->parse();
    }

    /**
     * Handle refund.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handleChargeRefunded(array $payload)
    {
        $account_id = array_get($payload, 'account');
        info('handleChargeRefunded', [$account_id]);

        $user = $this->getUserByConnectId($account_id);
        if (!$user) {
            info('handleChargeRefunded', ['user not found']);
            return false;
        }
        $this->setUser($user);

        $transaction_id = array_get($payload, 'data.object.id');
        try {
            $charge = \Stripe\Charge::retrieve(
                [
                    'id' => $transaction_id,
                    'expand' => ['invoice', 'invoice.subscription'],
                ],
                $this->getOptionsForStripeCall()
            );
        } catch (\Exception $e) {
            log_error($e);
            return false;
        }

        info('got a refund', [
            $charge->id,
            $charge->amount_refunded,
            array_get($charge, 'invoice.subscription.plan.product'),
        ]);

        if (!$charge->amount_refunded) {
            info('Zero refund, skipping');
            return false;
        }
        $product = Product::where(
            'payment_id',
            array_get($charge, 'invoice.subscription.plan.product')
        )->first();

        if (!$product) {
            logger()->debug('Product not found.', [
                array_get($charge, 'invoice.subscription.plan.product'),
            ]);
            return false;
        }

        $project = $product->projects()->first();
        if (!$project) {
            logger()->error('Got refund for product without project', [$charge->id]);
            return false;
        }

        $t = $this->project->transactions()
            ->where('remote_id', $transaction_id)
            ->first();
        if (!$t) {
            logger()->debug('transaction not found', [$transaction_id]);
            return false;
        }
        $t->update(['refunded' => true]);
        // mark as deleted to hide from calculations
        // TODO: delete only if fully refunded
        $t->delete();
        $t->record->recalculateDay($t->date->format("Y-m-d"));
        $t->record->recalculateMonth($t->date->format("Y-m"));

        return true;
    }

    /**
     * Handle new dispute.
     *
     * @param  array $payload
     * @return Boolean
     */
    public function handleChargeDisputeCreated(array $payload)
    {
        $account_id = array_get($payload, 'account');
        info('handleChargeDisputeCreated', [$account_id]);
        $user = $this->getUserByConnectId($account_id);
        if (!$user) {
            info('handleChargeDisputeCreated', ['user not found']);
            return false;
        }
        $this->setUser($user);

        # The dispute that was created
        $dispute = array_get($payload, 'data.object');
        info('dispute', [$dispute, array_get($dispute, 'balance_transactions.first.fee')]);

        # Retrieve the charge related to this dispute
        $charge = \Stripe\Charge::retrieve($dispute->charge, $this->getOptionsForStripeCall());

        # Issue a transfer reversal to recover the funds
        $this->reverse_transfer($charge);

        # Retrieve the platform account ID
        $platform_id = config('services.stripe.platform_id');

        # Create an account debit to recover dispute fee
        $debit = \Stripe\Transfer::create(
            [
                'amount' => array_get($dispute, 'balance_transactions.first.fee'),
                'currency' => 'jpy',
                'destination' => $platform_id,
                'description' => "Dispute fee for {$charge->id}",
            ],
            ['stripe_account' => $charge->destination]
        );
        return true;
    }

    public function handleChargeDisputeFundsReinstated(array $payload)
    {
        $account_id = array_get($payload, 'account');
        info('handleChargeDisputeFundsReinstated', [$account_id]);
        $user = $this->getUserByConnectId($account_id);
        if (!$user) {
            info('handleChargeDisputeFundsReinstated', ['user not found']);
            return false;
        }
        $this->setUser($user);

        # The dispute that was created
        $dispute = array_get($payload, 'data.object');

        # Retrieve the charge related to this dispute
        $charge = \Stripe\Charge::retrieve($dispute->charge, $this->getOptionsForStripeCall());

        # Create a transfer to the connected account to return the dispute fee
        $transfer = \Stripe\Transfer::create([
            'amount' => array_get($dispute, 'balance_transactions.second.net'),
            'currency' => "jpy",
            'destination' => $charge->destination,
        ]);

        # Retrieve the destination payment
        $payment = \Stripe\Charge::retrieve(
            [
                'id' => $transfer->destination_payment,
            ],
            [
                'stripe_account' => $transfer->destination,
            ]
        );

        # Update the description on the destination payment
        $payment->description = "Chargeback reversal for {$charge->id}";
        $payment->save();

        return true;
    }

    private function reverse_transfer(\Stripe\Charge $charge)
    {
        # Retrieve the transfer for the charge
        $transfer = \Stripe\Transfer::retrieve($charge->transfer);

        # Reverse the transfer and keep the application fee
        $transfer->reverse();
    }
}
