<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Paginate\Paginate;
use App\Http\Transformers\TransactionTransformer;
use App\Project;
use App\Services\Stripe\StripeTransactionsService;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class TransactionController extends ApiController
{
    /**
     * @var StripeTransactionsService
     */
    protected $stripe_transactions_service;

    /**
     * TransactionController __construct
     *
     * @param TransactionTransformer $transformer
     * @param StripeTransactionsService $stripeTransactionsService
     */
    public function __construct(
        TransactionTransformer $transformer,
        StripeTransactionsService $stripeTransactionsService
    ) {
        $this->transformer = $transformer;
        $this->stripe_transactions_service = $stripeTransactionsService;
    }

    /**
     * Show all transactions
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Project $project)
    {
        $transactions_query = $project
            ->revenue_transactions()
            ->withTrashed()
            ->with(['record.contact', 'record.plan']);

        if ($request->has('search') && $request->filled('search')) {
            $search = $request->input('search');

            if (mb_strlen($search) == 36 && is_uuid($search)) {
                return $this->respond([
                    'transactions' => [$transactions_query->findOrFail($search)],
                ]);
            }

            $transactions_query->search($search, 5);
        }

        return $this->respondWithPagination(new Paginate($transactions_query));
    }

    /**
     * Show single transaction
     *
     * @param Project $project
     * @param Transaction $transaction
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Project $project, Transaction $transaction)
    {
        if (!$project->revenue_transactions->contains($transaction->id)) {
            return $this->respondForbidden();
        }

        $transaction->load(['record.contact', 'record.plan', 'record.product']);

        return $this->respondWithTransformer($transaction);
    }

    /**
     * Refund transaction via stripe api
     *
     * @param Project $project
     * @param Transaction $transaction
     * @return \Illuminate\Http\JsonResponse
     */
    public function refund(Project $project, Transaction $transaction)
    {
        if (
            !$project->transactions->contains($transaction->id) ||
            empty($transaction->remote_id)
        ) {
            return $this->respondNotFound();
        }

        try {
            $this->stripe_transactions_service->refund($transaction->remote_id);

            return $this->respond('Transaction refunded successfully.');
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            return $this->respondError(Lang::getFromJson('Error refund transaction.'));
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }
}
