<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Lang;

class Subscribed
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
        if ($request->user() && !$request->user()->subscribed('default')) {
            // This user is not a paying customer...
            return $this->respondError(Lang::getFromJson("Payment required"), 402);
        }

        if (!$request->user()) {
            return $this->respondError("Unknown user", 400);
        }

        return $next($request);
    }

    /**
     * Respond with json error message.
     *
     * @param mixed $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondError($message, $code)
    {
        return response()->json(
            [
                'errors' => [
                    'message' => $message,
                    'status_code' => $code
                ]
            ],
            $code
        );
    }
}
