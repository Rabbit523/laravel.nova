<?php
namespace App\Traits;

use App\ConnectSubscriptionBuilder;
use App\Services\Stripe\StripeInvoicesService;
use App\Services\Stripe\StripeTransactionsService;

use InvalidArgumentException;
use Stripe\Token as StripeToken;
use Stripe\Charge as StripeCharge;
use Stripe\Invoice as StripeInvoice;
use Stripe\Customer as StripeCustomer;
use Stripe\InvoiceItem as StripeInvoiceItem;
use Stripe\Error\InvalidRequest as StripeErrorInvalidRequest;

use Laravel\Cashier\Billable as ParentTrait;
use Laravel\Cashier\Invoice;

trait ConnectBillable
{
    use ParentTrait;

    /**
     * The Connect Account.
     *
     * @var \App\User
     */
    protected static $connectAccount;

    /**
     * Get the Connect Account.
     *
     * @return \App\User
     */
    public function getConnectAccount()
    {
        return static::$connectAccount ?: $this->user;
    }

    /**
     * Set the Connect Account.
     *
     * @param  \App\User $account
     * @return $this
     */
    public function setConnectAccount(\App\User $account)
    {
        static::$connectAccount = $account;
        return $this;
    }

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int    $amount
     * @param  array  $options
     * @return \Stripe\Charge
     * @throws \InvalidArgumentException
     */
    public function charge($amount, array $options = [])
    {
        $options = array_merge(['currency' => $this->preferredCurrency()], $options);

        $options['amount'] = $amount;

        if (!array_key_exists('source', $options) && $this->stripe_id) {
            $options['customer'] = $this->stripe_id;
        }

        if (!array_key_exists('source', $options) && !array_key_exists('customer', $options)) {
            throw new InvalidArgumentException('No payment source provided.');
        }

        if ($this->applicationFee()) {
            $options['application_fee_amount'] = $this->applicationFee();
        }

        return StripeCharge::create($options, $this->getOptionsForStripeCall());
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string  $charge
     * @param  array   $options
     * @return \Stripe\Refund
     * @throws \InvalidArgumentException
     */
    public function refund($charge, array $options = [])
    {
        return (new StripeTransactionsService)->setUser($this->getConnectAccount())->refund($charge);
    }

    /**
     * Add an invoice item to the customer's upcoming invoice.
     *
     * @param  string  $description
     * @param  int     $amount
     * @param  array   $options
     * @return \Stripe\InvoiceItem
     * @throws \InvalidArgumentException
     */
    public function tab($description, $amount, array $options = [])
    {
        if (!$this->stripe_id) {
            throw new InvalidArgumentException(
                class_basename($this) .
                    ' is not a Stripe customer. See the createAsStripeCustomer method.'
            );
        }

        $stripe_id = $this->stripe_id;

        $options = array_merge(
            [
                'customer' => $stripe_id,
                'amount' => $amount,
                'currency' => $this->preferredCurrency(),
                'description' => $description,
            ],
            $options
        );

        return StripeInvoiceItem::create($options, $this->getOptionsForStripeCall());
    }

    /**
     * Invoice the billable entity outside of regular billing cycle.
     *
     * @param  array  $options
     * @return \Stripe\Invoice|bool
     */
    public function invoice(array $options = [])
    {
        $stripe_id = $this->stripe_id;
        if (!$stripe_id) {
            return false;
        }

        $parameters = array_merge($options, ['customer' => $stripe_id]);

        try {
            return StripeInvoice::create($parameters, $this->getOptionsForStripeCall())->pay();
        } catch (StripeErrorInvalidRequest $e) {
            return false;
        }
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Stripe\StripeObject|null
     * @throws \Stripe\Error\Api
     */
    public function findInvoice($id)
    {
        $stripeInvoice = (new StripeInvoicesService())->setUser($this->getConnectAccount())->getInvoice($id);
        return new Invoice($this, $stripeInvoice);
    }


    /**
     * Begin creating a new subscription.
     *
     * @param  string  $subscription
     * @param  string  $plan
     * @return \App\ConnectSubscriptionBuilder
     */
    public function newSubscription($subscription, $plan)
    {
        return new ConnectSubscriptionBuilder($this, $subscription, $plan);
    }

    /**
     * Update customer's credit card.
     *
     * @param  string  $token
     * @return void
     */
    public function updateCard($token)
    {
        $customer = $this->asStripeCustomer();

        $token = StripeToken::retrieve($token, $this->getOptionsForStripeCall());

        // If the given token already has the card as their default source, we can just
        // bail out of the method now. We don't need to keep adding the same card to
        // a model's account every time we go through this particular method call.
        if ($token[$token->type]->id === $customer->default_source) {
            return;
        }

        $card = $customer->sources->create(['source' => $token], $this->getOptionsForStripeCall());

        $customer->default_source = $card->id;

        $customer->save();

        // Next we will get the default source for this model so we can update the last
        // four digits and the card brand on the record in the database. This allows
        // us to display the information on the front-end when updating the cards.
        $source = $customer->default_source
            ? $customer->sources->retrieve($customer->default_source)
            : null;

        $this->fillCardDetails($source);

        $this->save();
    }

    /**
     * Create a Stripe customer for the given Stripe model.
     *
     * @param  array  $options
     * @return \Stripe\Customer
     */
    public function createAsStripeCustomer(array $options = [])
    {
        $options = array_key_exists('email', $options)
            ? $options
            : array_merge($options, ['email' => $this->email]);

        // Here we will create the customer instance on Stripe and store the ID of the
        // user from Stripe. This ID will correspond with the Stripe user instances
        // and allow us to retrieve users from Stripe later when we need to work.
        $customer = StripeCustomer::create($options, $this->getOptionsForStripeCall());

        $this->stripe_id = $customer->id;
        $this->save();

        return $customer;
    }

    /**
     * Get the Stripe customer for the Stripe model.
     *
     * @return \Stripe\Customer
     */
    public function asStripeCustomer()
    {
        return StripeCustomer::retrieve($this->stripe_id, $this->getOptionsForStripeCall());
    }

    /**
     * Get the application Fee percentage.
     *
     * @return int|float
     */
    public function applicationFeePercent()
    {
        return 0;
    }
    /**
     * Get the application Fee.
     *
     * @return int|float
     */
    public function applicationFee()
    {
        return 0;
    }

    /**
     * Get options for stripe call with STRIPE_ACCOUNT and API_KEY.
     *
     * @return array
     */
    public function getOptionsForStripeCall()
    {
        return array_filter([
            "api_key" => $this->getStripeKey(),
            "stripe_account" => ($this->getConnectAccount()
                ? $this->getConnectAccount()->connect_id
                : null),
        ]);
    }
}
