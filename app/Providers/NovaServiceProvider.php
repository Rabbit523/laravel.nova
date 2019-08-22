<?php

namespace App\Providers;

use Laravel\Cashier\Cashier;
use Laravel\Nova\Nova;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Cards\Help;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        Cashier::useCurrency('jpy', '¥');

        Nova::serving(function (ServingNova $event) {
            Nova::style('nova-theme', __DIR__ . '/../../resources/assets/css/nova-theme.css');
        });
    }

    /**
     * Register the Nova routes.
     *
     * @return void
     */
    protected function routes()
    {
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withPasswordResetRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewNova', function ($user) {
            return $user->acl >= 10;
        });
    }

    /**
     * Get the cards that should be displayed on the Nova dashboard.
     *
     * @return array
     */
    protected function cards()
    {
        return [
            new \App\Nova\Metrics\NewUsers(),
            new \App\Nova\Metrics\UsersPerDay(),
            new \App\Nova\Metrics\ActiveUsersPerDay(),
            new \App\Nova\Metrics\UsersPerPlan(),
            new \App\Nova\Metrics\ProjectsPerUser(),
            new \Tightenco\NovaGoogleAnalytics\PageViewsMetric(),
            new \Tightenco\NovaGoogleAnalytics\VisitorsMetric(),
            new \Tightenco\NovaGoogleAnalytics\MostVisitedPagesCard(),
            new \Tightenco\NovaGoogleAnalytics\ReferrersList()
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array
     */
    public function tools()
    {
        return [
            new \Tightenco\NovaStripe\NovaStripe(),
            new \Naif\MailchimpTool\MailchimpTool(),
            new \Tightenco\NovaReleases\AllReleases(),
            new \SeteMares\NovaNotifications\NovaNotifications()
            // new \Spatie\BackupTool\BackupTool(),
            // new \Kristories\Novassport\Novassport()
        ];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
