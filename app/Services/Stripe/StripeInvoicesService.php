<?php
namespace App\Services\Stripe;

class StripeInvoicesService extends BaseStripeService
{
    /**
     * Get all invoices from stripe api
     *
     * @param array $request_data - filters
     * @return \Stripe\Collection
     * @throws \Stripe\Error\Api
     */
    public function getAllInvoices($request_data = [])
    {
        return \Stripe\Invoice::all($request_data, $this->getOptionsForStripeCall());
    }

    /**
     * Get single invoice from stripe api
     *
     * @param string $invoice_id
     * @return \Stripe\StripeObject
     * @throws \Stripe\Error\Api
     */
    public function getInvoice($invoice_id)
    {
        return \Stripe\Invoice::retrieve(
            [
                'id' => $invoice_id,
                'expand' => ['charge', 'default_source', 'subscription'],
            ],
            $this->getOptionsForStripeCall()
        );
    }
}
