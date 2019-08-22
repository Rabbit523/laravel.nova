<?php

namespace App\Services\Stripe;

use Illuminate\Support\Facades\Cache;

class StripeBalanceService extends BaseStripeService
{
    /**
     * Get prefix name with user connected account
     *
     * @param $name - prefix name
     * @return string
     */
    public function getCachePrefix($name)
    {
        return $name . ':' . $this->getUserConnectId();
    }

    /**
     * Get current balance from cache, add it in if not exists
     *
     * @return mixed
     */
    public function getCurrentBalance()
    {
        return Cache::remember(
            $this->getCachePrefix('current_balance'),
            now()->addMinutes(10),
            function () {
                return \Stripe\Balance::retrieve($this->getOptionsForStripeCall());
            }
        );
    }

    /**
     * Get balance for latest transactions from cache, add it in if not exists
     *
     * @param $limit - limit of transactions
     * @return mixed
     */
    public function getBalanceHistory($limit)
    {
        return Cache::remember(
            $this->getCachePrefix('payouts_list'),
            now()->addMinutes(10),
            function () use ($limit) {
                return \Stripe\BalanceTransaction::all(
                    ['limit' => $limit, 'type' => 'payout'],
                    $this->getOptionsForStripeCall()
                )->data;
            }
        );
    }
}
