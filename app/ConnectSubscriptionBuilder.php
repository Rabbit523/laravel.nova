<?php
namespace App;

use Laravel\Cashier\Exceptions\SubscriptionCreationFailed;
use Laravel\Cashier\SubscriptionBuilder;

class ConnectSubscriptionBuilder extends SubscriptionBuilder
{

    /**
     * The application fee percent.
     *
     * @var int|float
     */
    protected $feePercent = 0;

    /**
     * The application fee to apply to a new subscription.
     *
     * @param  int|float  $fee
     * @return $this
     */
    public function setApplicationFeePercent($fee)
    {
        $this->feePercent = $fee;

        return $this;
    }

    /**
     * Create a new Stripe subscription.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Laravel\Cashier\Subscription
     */
    public function create($token = null, array $options = [])
    {
        $customer = $this->getStripeCustomer($token, $options);

        $subscription = $customer->subscriptions->create(
            $this->buildPayload(),
            ["stripe_account" => $this->owner->user ? $this->owner->user->connect_id : null]
        );

        if (in_array($subscription->status, ['incomplete', 'incomplete_expired'])) {
            $subscription->cancel();

            throw SubscriptionCreationFailed::incomplete($subscription);
        }

        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialExpires;
        }

        return $this->owner->subscriptions()->create([
            'name' => $this->name,
            'stripe_id' => $subscription->id,
            'stripe_plan' => $this->plan,
            'quantity' => $this->quantity,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);
    }

    /**
     * Build the payload for subscription creation.
     *
     * @return array
     */
    protected function buildPayload()
    {
        return array_filter([
            'billing_cycle_anchor' => $this->billingCycleAnchor,
            'coupon' => $this->coupon,
            'metadata' => $this->metadata,
            'plan' => $this->plan,
            'quantity' => $this->quantity,
            'application_fee_percent' => $this->feePercent,
            'tax_percent' => $this->getTaxPercentageForPayload(),
            'trial_end' => $this->getTrialEndForPayload(),
        ]);
    }
}
