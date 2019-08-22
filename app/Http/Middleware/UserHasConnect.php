<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Lang;

class UserHasConnect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!user() || !user()->connect_id) {
            return response()->json(
                [
                    'errors' => [
                        'message' => Lang::getFromJson(
                            'Connect is not enabled on the account.'
                        ),
                        'status_code' => 400,
                    ],
                ],
                400
            );
        }

        return $next($request);
    }
}
