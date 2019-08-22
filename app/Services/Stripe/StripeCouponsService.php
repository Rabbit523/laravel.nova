<?php
namespace App\Services\Stripe;

use Illuminate\Support\Facades\Cache;

class StripeCouponsService extends BaseStripeService
{
    /**
     * Get prefix for user cache
     *
     * @return string
     */
    public function getUserCachePrefix()
    {
        return 'stripe.coupons:' . $this->getUserConnectId();
    }

    /**
     * Get prefix for single coupon
     *
     * @param $coupon_id - coupon id
     * @return string
     */
    public function getCouponCachePrefix($coupon_id)
    {
        return $this->getUserCachePrefix() . '.coupon:' . $coupon_id;
    }

    /**
     * Method for forget and reload all coupons in cache again
     *
     * @return void
     */
    public function reloadAllCouponsFromCache()
    {
        Cache::forget($this->getUserCachePrefix());
        $this->getCoupons();
    }

    /**
     * Get coupons from cache, add them in if not exists
     *
     * @return mixed
     */
    public function getCoupons()
    {
        return Cache::remember($this->getUserCachePrefix(), now()->addHours(24), function () {
            //TODO: think about pagination in future
            $coupons = \Stripe\Coupon::all(['limit' => 100], $this->getOptionsForStripeCall());
            return $coupons->data;
        });
    }

    /**
     * Get single coupon from cache, add them in if not exists
     *
     * @param $coupon_id - coupon id
     * @return mixed
     */
    public function getCoupon($coupon_id)
    {
        return Cache::remember(
            $this->getCouponCachePrefix($coupon_id),
            now()->addHours(24),
            function () use ($coupon_id) {
                return \Stripe\Coupon::retrieve($coupon_id, $this->getOptionsForStripeCall());
            }
        );
    }

    /**
     * Create coupon and store it in cache
     *
     * @param $request_data - request params
     * @return \Stripe\ApiResource
     */
    public function createCoupon($request_data)
    {
        $coupon = \Stripe\Coupon::create($request_data, $this->getOptionsForStripeCall());

        $this->reloadAllCouponsFromCache();

        return $coupon;
    }

    /**
     * update coupon and store in cache
     *
     * @param $request - request data
     * @param $coupon_id - coupon id
     * @return \Stripe\ApiResource
     */
    public function updateCoupon($request, $coupon_id)
    {
        $coupon = \Stripe\Coupon::update(
            $coupon_id,
            ['name' => $request['name']],
            ['stripe_account' => user()->connect_id]
        );

        Cache::forget($this->getCouponCachePrefix($coupon_id));
        $this->reloadAllCouponsFromCache();

        return $coupon;
    }

    /**
     * Remove coupons from stripe and cache
     *
     * @param $coupon_id - coupon id
     * @return \Stripe\ApiResource
     */
    public function deleteCoupon($coupon_id)
    {
        $coupon = $this->getCoupon($coupon_id);
        $coupon->delete();

        Cache::forget($this->getCouponCachePrefix($coupon_id));
        $this->reloadAllCouponsFromCache();

        return $coupon;
    }
}
