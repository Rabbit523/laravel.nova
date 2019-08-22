<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\ShowInvoices;
use App\Services\Stripe\StripeInvoicesService;
use Illuminate\Support\Facades\Lang;

class InvoiceController extends ApiController
{
    /**
     * @var StripeInvoicesService
     */
    protected $invoices_service;

    /**
     * InvoiceController __construct
     *
     * @param StripeInvoicesService $stripeInvoicesService
     */
    public function __construct(StripeInvoicesService $stripeInvoicesService)
    {
        $this->middleware('has.connect');
        $this->invoices_service = $stripeInvoicesService;
    }

    /**
     * All invoices from stripe api
     *
     * @param ShowInvoices $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(ShowInvoices $request)
    {
        try {
            return $this->respond([
                'invoices' => $this->invoices_service->getAllInvoices(
                    $request->only['filters']
                ),
            ]);
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            return $this->respondError(Lang::getFromJson('Error loading invoices.'));
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }

    /**
     * Single invoice from stripe api
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            return $this->respond([
                'invoice' => $this->invoices_service->getInvoice($id),
            ]);
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            return $this->respondError(Lang::getFromJson('Error loading invoice.'));
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }
}
