<?php


namespace App\Providers;

use App\Services\Google\BaseGoogleService;
use App\Services\Google\Contracts\GoogleContactService as GoogleContactServiceInterface;
use App\Services\Google\Contracts\BaseGoogleService as BaseGoogleServiceInterface;
use App\Services\Google\GoogleContactService;
use Illuminate\Support\ServiceProvider;

class GoogleServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(BaseGoogleServiceInterface::class, BaseGoogleService::class);
        $this->app->bind(GoogleContactServiceInterface::class, GoogleContactService::class);
    }
}
