<?php
namespace App\Services\Stripe;

use Illuminate\Support\Facades\Cache;
use Stripe\Plan;
use Stripe\Product;

class StripePlansService extends BaseStripeService
{
    /**
     * Get plans list
     *
     * @return mixed
     */
    public function getPlans()
    {
        $list = Plan::all(['limit' => 100], $this->getOptionsForStripeCall());
        return $list->data;
    }

    /**
     * Get plan
     *
     * @param $id - plan id
     * @return ApiResource
     */
    public function getPlan($id)
    {
        return Plan::retrieve($id, $this->getOptionsForStripeCall());
    }

    /**
     * Get product
     *
     * @param $id - product id
     * @return ApiResource
     */
    public function getProduct($id)
    {
        return Product::retrieve($id, $this->getOptionsForStripeCall());
    }

    /**
     * Create plan
     *
     * @param $data - request data
     * @return ApiResource
     */
    public function createPlan($data)
    {
        $data['nickname'] = array_pull($data, 'name');
        $data['trial_period_days'] = array_pull($data, 'trial_days');
        $data = array_only($data, [
            'nickname',
            'trial_period_days',
            'interval',
            'interval_count',
            'currency',
            'amount',
            'billing_day',
            'billing_scheme',
            'per_unit',
            'product',
        ]);
        $data['currency'] = 'jpy'; // FIXME: force JPY for now
        $plan = Plan::create($data, $this->getOptionsForStripeCall());

        return $plan;
    }

    /**
     * Create product
     *
     * @param $data - request data
     * @return ApiResource
     */
    public function createProduct($data)
    {
        $product = Product::create(
            array_only($data, ['name', 'description', 'type']),
            $this->getOptionsForStripeCall()
        );

        return $product;
    }

    /**
     * Update plan
     *
     * @param $data - request data
     * @param $id - plan id
     * @return ApiResource
     */
    public function updatePlan($data, $id)
    {
        $plan = Plan::update(
            $id,
            [
                'nickname' => array_get($data, 'name'),
                'trial_period_days' => array_get($data, 'trial_days'),
            ],
            $this->getOptionsForStripeCall()
        );

        return $plan;
    }

    /**
     * Update product
     *
     * @param $description - new description
     * @param $id - product id
     * @return ApiResource
     */
    public function updateProduct($description, $id)
    {
        $product = Product::update(
            $id,
            compact('description'),
            $this->getOptionsForStripeCall()
        );

        return $product;
    }

    /**
     * Remove plan from stripe
     *
     * @param $id - plan id
     * @return ApiResource
     */
    public function deletePlan($id)
    {
        $plan = $this->getPlan($id);
        $plan->delete();

        return $plan;
    }

    /**
     * Remove product from stripe
     *
     * @param $id - product id
     * @return ApiResource
     */
    public function deleteProduct($id)
    {
        $product = $this->getProduct($id);
        $product->delete();

        return $product;
    }
}
