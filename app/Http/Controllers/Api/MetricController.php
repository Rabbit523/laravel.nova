<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\MetricRequest;

class MetricController extends ApiController
{
    /**
     * List the metrics.
     *
     * @param  \App\Http\Requests\Api\MetricRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(MetricRequest $request)
    {
        return $request->availableMetrics();
    }

    /**
     * Get the specified metric's value.
     *
     * @param  \App\Http\Requests\Api\MetricRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(MetricRequest $request)
    {
        return $this->respond([
            'value' => $request->metric()->resolve($request)
        ]);
    }
}
