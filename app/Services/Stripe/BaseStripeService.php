<?php
namespace App\Services\Stripe;

use App\User;

class BaseStripeService
{

    /**
     * @var User
     */
    protected $user;

    /**
     * BaseStripeService __construct
     */
    public function __construct()
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Set user
     *
     * @param User $user
     * @return $this
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get current user
     *
     * @return User
     */
    private function getUser()
    {
        return $this->user ?: user();
    }

    /**
     * Get user stripe connect id
     *
     * @return string
     */
    protected function getUserConnectId()
    {
        return $this->getUser()->connect_id;
    }

    /**
     * Get stripe options with user connect id
     *
     * @param array|null
     * @return array
     */
    protected function getOptionsForStripeCall($stripe_params = [])
    {
        if (empty($stripe_params['stripe_account'])) {
            $stripe_params['stripe_account'] = $this->getUserConnectId();
        }

        return $stripe_params;
    }
}
