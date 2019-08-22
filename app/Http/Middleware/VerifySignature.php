<?php

namespace App\Http\Middleware;

use Closure;

class VerifyKinchakuSignature
{
    public function handle($request, Closure $next)
    {
        $signature = $request->header('Kinchaku-Signature');

        if (!$signature) {
            return response(['error' => "Missing signature"], 400);
        }

        if (!$this->isValid($signature, $request->getContent())) {
            return response(['error' => "Invalid signature"], 400);
        }

        return $next($request);
    }

    protected function isValid(string $signature, string $payload): bool
    {
        $secret = config('services.kinchaku.signing_secret');

        if (empty($secret)) {
            logger()->info("Kinchaku webhook signature not set");
            return true;
        }

        $computedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($signature, $computedSignature);
    }
}
