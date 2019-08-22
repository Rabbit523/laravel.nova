<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        \SocialiteProviders\Manager\SocialiteWasCalled::class => [
            'SocialiteProviders\Microsoft\MicrosoftExtendSocialite@handle',
            'SocialiteProviders\HubSpot\HubSpotExtendSocialite@handle',
        ],

        'Illuminate\Mail\Events\MessageSent' => ['App\Listeners\LogSentMessage'],
        'Illuminate\Auth\Events\Login'       => ['App\Listeners\LatestLogin']
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
