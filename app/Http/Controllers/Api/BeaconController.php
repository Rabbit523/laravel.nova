<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\BeaconRequest;
use App\Beacon;
use App\Services\Stripe\StripePlansService;

class BeaconController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->respond(['beacons' => user()->beacons]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\BeaconRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(BeaconRequest $request)
    {
        $data = $request->validated();
        array_pull($data, 'project_id');
        $beacon = $request->project->beacon()->firstOrCreate($data);
        if (user()->connect_id) {
            $this->attachProject($beacon, $request->project);
        }
        return $this->respond(compact('beacon'));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Beacon  $beacon
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Beacon $beacon)
    {
        return $this->respond(compact('beacon'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\BeaconRequest  $request
     * @param  \App\Beacon  $beacon
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(BeaconRequest $request, Beacon $beacon)
    {
        $data = $request->validated();
        array_pull($data, 'project_id');
        array_pull($data, 'project');
        $beacon->fill($data);
        $beacon->save();
        if (user()->connect_id) {
            $this->attachProject($beacon, $beacon->project);
        }
        return $this->respond(compact('beacon'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Http\Requests\BeaconRequest  $request
     * @param  \App\Beacon  $beacon
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(BeaconRequest $request, Beacon $beacon)
    {
        $beacon->delete();

        return ok();
    }

    private function attachProject($beacon, $project)
    {
        // make a connection between product and project here
        $beacon_plans = collect($beacon->plans)
            ->flatten()
            ->toArray();

        $products = user()->products_sold;
        $products = $products->filter(function ($product) use ($beacon_plans) {
            return $product->plans->whereIn('id', $beacon_plans)->count() > 0;
        });

        $products->each(function ($product) use ($project) {
            $product->projects()->syncWithoutDetaching([$project->id]);
        });

        $service = app(StripePlansService::class);

        // sync plan with stripe
        $project->plans->whereIn('id', $beacon_plans)->each(function ($plan) use ($service) {
            // plan already in stripe
            if ($plan->managed && $plan->payment_id) {
                return;
            }
            try {
                $data = $plan->toArray();
                $data['product'] = [
                    'name' => $plan->product->name,
                ];
                $result = $service->createPlan($data);
                $plan->update([
                    'payment_id' => $result->id,
                    'payment_type' => 'stripe',
                    'managed' => true,
                ]);

                $plan->product->update([
                    'payment_id' => $result->product,
                    'payment_type' => 'stripe',
                    'managed' => true,
                ]);
            } catch (\Exception $e) {
                log_error($e);
                return;
            }
        });
    }
}
