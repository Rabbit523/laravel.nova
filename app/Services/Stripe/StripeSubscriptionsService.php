<?php
namespace App\Services\Stripe;

class StripeSubscriptionsService extends BaseStripeService
{
    /**
     * Get all subscriptions from strippe account
     *
     * @return \Stripe\Collection
     * @throws \Stripe\Error\Api
     */
    public function getSubscriptions()
    {
        return \Stripe\Subscription::all(
            ['limit' => 100],
            $this->getOptionsForStripeCall()
        );
    }
}