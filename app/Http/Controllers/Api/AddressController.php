<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\Relation;

use App\Address;

class AddressController extends ApiController
{
    protected $model;
    protected $id;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $model = Relation::getMorphedModel($request->route()->parameter('model'));
            $this->id = $request->route()->parameter('id');

            if (empty($model) || empty($this->id)) {
                return $this->respondNotFound();
            }

            $this->model = resolve($model)
                ->where('id', $this->id)
                ->firstOrFail();
            if (!$model) {
                return $this->respondNotFound();
            }

            return $next($request);
        });
    }

    /**
     * Get model addresses.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return $this->respond([
            'addresses' => $this->model->addresses
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->model->addresses()->create($request->get('address'));
        return ok();
    }

    /**
     * Update record in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $model
     * @param  string $id
     * @param  \App\Address             $address
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $model, $id, Address $address)
    {
        $address->update($request->get('address'));
        return ok();
    }

    /**
     * Delete record.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $model
     * @param  string $id
     * @param  \App\Address             $address
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request, $model, $id, Address $address)
    {
        $address->delete();
        return ok();
    }
}
