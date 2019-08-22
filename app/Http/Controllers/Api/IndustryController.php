<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Industry;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IndustryController extends ApiController
{
    public function __construct()
    {
    }

    public function index()
    {
        $industries = Cache::remember('industries', now()->addMinutes(60), function () {
            return Industry::all();
        });
        return $this->respond(['industries' => $industries]);
    }
}
