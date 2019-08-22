<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Services\Stripe\StripeSubscriptionsService;
use Illuminate\Support\Facades\Lang;

class SubscriptionController extends ApiController
{
    public function __construct()
    { }

    public function plans()
    {
        return $this->respond(['plans' => get_plans()]);
    }

    public function invoices()
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        $user = user();
        if (!$user->stripe_id) {
            return $this->respond(['invoices' => []]);
        }
        $invoices = \Stripe\Invoice::all(["limit" => 3, "customer" => $user->stripe_id]);
        return $this->respond(['invoices' => $invoices->data]);
    }

    /**
     * Show user subscriptions
     *
     * @param StripeSubscriptionsService $stripeSubscriptionsService
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(StripeSubscriptionsService $stripeSubscriptionsService)
    {
        try {
            return $this->respond([
                'subscriptions' => $stripeSubscriptionsService->getSubscriptions()->data
            ]);
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            return $this->respondError(Lang::getFromJson('Error get subscriptions '));
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }
}
