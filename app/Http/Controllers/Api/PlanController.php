<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Lang;
use App\Product;
use App\Plan;
use App\Project;

use App\Http\Requests\Api\CreatePlan;
use App\Http\Requests\Api\UpdatePlan;
use App\Http\Requests\Api\DeletePlan;
use App\Services\Stripe\StripePlansService;

class PlanController extends ApiController
{
    /**
     * Create a Product controller instance.
     *
     * @param StripePlansService $service
     */
    public function __construct(StripePlansService $service)
    {
        $this->service = $service;
        $this->middleware('has.connect')->only(['create']);
    }

    /**
     * Get plan by id.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $plan = Plan::with(['product'])
            ->where('id', $id)
            ->firstOrFail();
        if ($plan->product->user_id != auth()->id() && user()->acl < 9) {
            return $this->respondForbidden();
        }

        return $this->respond(compact('plan'));
    }

    /**
     * Create a new plan without existing product
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request, Project $project)
    {
        $data = $request->get('plan');
        if (!array_get($data, 'product')) {
            return $this->respondError('Empty product info');
        }
        $data['currency'] = $project->currency;
        $data['interval'] = array_get($data, 'interval', 'month');

        try {
            $result = $this->service->createPlan($data);
            $data['payment_id'] = $result->id;
            $data['payment_type'] = 'stripe';
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondError($e->getMessage());
        }

        $product = user()->firstOrCreateProduct(
            [
                'payment_id' => $result->product,
                'payment_type' => 'stripe',
            ],
            [
                'sold' => true,
                'managed' => true,
                'payment_id' => $result->product,
                'payment_type' => 'stripe',
                'name' => array_get($data, 'product.name'),
                'slug' => str_slug_u(array_get($data, 'product.name')),
                'description' => array_get($data, 'product.description'),
            ]
        );
        if (!$product) {
            return $this->respondError('Product creation failed.');
        }

        // $data['slug'] = str_slug_u(array_get($data, 'name'));
        $data['managed'] = true;
        array_pull($data, 'product');
        $plan = $product
            ->plans()
            ->lockForUpdate()
            ->firstOrCreate($data);
        if (!$plan) {
            return $this->respondError('Plan creation failed.');
        }

        $product->projects()->syncWithoutDetaching([$project->id]);
        return $this->respond(compact('plan'));
    }

    /**
     * Create a new plan and return the plan if successful.
     *
     * @param  CreatePlan $request
     * @param  Product    $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreatePlan $request, Product $product)
    {
        $data = $request->get('plan');
        $data['interval'] = array_get($data, 'interval', 'month');
        if (array_get($data, 'managed', false) && user()->connect_id) {
            $data['product'] = $product->payment_id;
            $data['currency'] = "jpy";
            $data['managed'] = true;
            try {
                $result = $this->service->createPlan($data);
            } catch (\Exception $e) {
                log_error($e);
                return $this->respondError($e->getMessage());
            }
            // plan will be created via webhook
            return $this->respond(['plan' => $data]);
        }
        // $data['slug'] = str_slug_u(array_get($data, 'name'));
        $plan = $product->plans()->create($data);

        return $this->respond(compact('plan'));
    }

    /**
     * Update the plan given by its id and return the plan if successful.
     *
     * @param  UpdatePlan  $request
     * @param  Plan        $plan
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePlan $request, Plan $plan)
    {
        $data = $request->get('plan');
        $result = $plan->update([
            'name' => array_get($data, 'name'),
            // 'slug' => str_slug_u(array_get($data, 'name')),
            'trial_days' => array_get($data, 'trial_days'),
            'addons' => array_get($data, 'addons'),
            'description' => array_get($data, 'description'),
            'tax_included' => array_get($data, 'tax_included'),
        ]);

        if ($plan->managed && $plan->payment_id && user()->connect_id) {
            // updating managed plan
            $this->service->updatePlan($data, $plan->payment_id);
        } elseif (
            array_get($data, 'managed')
            && !$plan->payment_id
            && user()->connect_id
            && $plan->product->payment_id
        ) {
            // plan wasn't managed before, create it in stripe
            try {
                $data = $plan->toArray();
                $data['product'] = $plan->product->payment_id;
                $result = $this->service->createPlan($data);
            } catch (\Exception $e) {
                log_error($e);
                return $this->respondError($e->getMessage());
            }
            $plan->managed = true;
            $plan->payment_id = $result->id;
            $plan->payment_type = 'stripe';
            $plan->save();
        }

        return $this->respond(compact('plan'));
    }

    /**
     * Delete the plan given by its id.
     *
     * @param  DeletePlan $request
     * @param  Plan       $plan
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(DeletePlan $request, Plan $plan)
    {
        if ($plan->managed && $plan->payment_id && user()->connect_id) {
            $this->service->deletePlan($plan->payment_id);
        }
        $plan->delete();

        return ok();
    }

    /**
     * Import plans from csv
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $result = Storage::makeDirectory('/csv/plans/' . user()->id);
        if (!$result) {
            return $this->respondError(
                Lang::getFromJson(
                    "There has been an error while saving a file. Please try again."
                ),
                500
            );
        }

        $file = request()->file('file');
        // TODO: check file type first. gz,zip,csv

        $path = $file->store('/csv/plans/' . user()->id);
        try {
            Plan::import(Storage::path($path));
        } catch (\Exception $e) {
            return $this->respondError($e->getMessage(), 400);
        }

        return $this->respondNoContent();
    }
}
