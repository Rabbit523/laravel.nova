<?php

namespace App\Http\Middleware;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        if (auth()->check()) {
            \App::setLocale(user()->language);
        } elseif ($request->has('user.language')) {
            \App::setLocale($request->input('user.language'));
        }
        return $next($request);
    }
}
