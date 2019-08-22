<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\Relation;

use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use App\Notifications\AppChannel;
use App\Notifications\NotificationRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Client;

use SeteMares\Freee\Provider as FreeeProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Relation::morphMap([
            'contact' => 'App\Contact',
            'user' => 'App\User',
            'project' => 'App\Project'
        ]);

        Cashier::useCurrency('jpy', '¥');

        Passport::routes();
        Client::creating(function (Client $client) {
            $client->incrementing = false;
            $client->id = Str::uuid()->toString();
        });
        Client::retrieved(function (Client $client) {
            $client->incrementing = false;
        });
        \App\User::updating(function (\App\User $model) {
            Cache::forget('context.id:' . $model->id);
        });
        // Passport::cookie('custom_name'); // custom header?

        Event::listen('Illuminate\Notifications\Events\NotificationSent', function ($event) {
            if ($event->channel == 'App\Notifications\AppChannel') {
                return;
            }
            $channel = new AppChannel(new NotificationRepository());
            $channel->send($event->notifiable, $event->notification, false);
        });

        Event::listen('Illuminate\Notifications\Events\NotificationFailed', function ($event) {
            $channel = new AppChannel(new NotificationRepository());
            $channel->send($event->notifiable, $event->notification, true);
        });
        $this->bootFreeeSocialite();
    }

    private function bootFreeeSocialite()
    {
        $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');
        $socialite->extend('freee', function ($app) use ($socialite) {
            $config = $app['config']['services.freee'];
            return $socialite->buildProvider(FreeeProvider::class, $config);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Passport::ignoreMigrations();

        if ($this->app->environment() === 'local') {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }
    }
}
