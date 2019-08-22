<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\CreateCoupon;
use App\Services\Stripe\StripeCouponsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class CouponController extends ApiController
{

    /**
     * @var StripeCouponsService
     */
    protected $coupons_servise;

    /**
     * CouponController constructor
     *
     * @param StripeCouponsService $couponsService
     */
    public function __construct(StripeCouponsService $couponsService)
    {
        $this->coupons_servise = $couponsService;
        $this->middleware('has.connect');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            return $this->respond([
                'coupons' => $this->coupons_servise->getCoupons(),
            ]);
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            return $this->respondError(Lang::getFromJson('Error loading coupons.'));
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  CreateCoupon  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateCoupon $request)
    {
        try {
            return $this->respond([
                'coupon' => $this->coupons_servise->createCoupon($request->all()),
            ]);
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            // TODO: provide frontend with some details?
            return $this->respondError(Lang::getFromJson('Error creating coupon.'));
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            return $this->respond([
                'coupon' => $this->coupons_servise->getCoupon($id),
            ]);
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            return $this->respondError(Lang::getFromJson('Coupon not found.'), 404);
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            return $this->respond([
                'coupon' => $this->coupons_servise->updateCoupon($request->all(), $id),
            ]);
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            return $this->respondError(Lang::getFromJson('Error updating coupon.'));
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $this->coupons_servise->deleteCoupon($id);
            return ok();
        } catch (\Stripe\Error\Base $e) {
            log_error($e);
            return $this->respondError(Lang::getFromJson('Error deleting coupon.'));
        } catch (\Exception $e) {
            log_error($e);
            return $this->respondInternalError();
        }
    }
}
