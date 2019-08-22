<?php

namespace App\Services\Stripe;

class StripeTransactionsService extends BaseStripeService
{
    /**
     * Refund transaction on stripe api
     *
     * @param string $charge
     * @return \Stripe\ApiResource
     */
    public function refund(string $charge)
    {
        return \Stripe\Refund::create(
            [
                "charge" => $charge,
                "refund_application_fee" => true,
            ],
            $this->getOptionsForStripeCall()
        );
    }
}
