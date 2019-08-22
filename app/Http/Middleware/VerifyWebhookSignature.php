<?php

namespace App\Http\Middleware;

use Closure;
use Stripe\WebhookSignature;
use Stripe\Error\SignatureVerification;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as Config;
use App\Integration;

final class VerifyWebhookSignature
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The configuration repository instance.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    public function __construct(Application $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, Closure $next)
    {
        try {
            $key = $request->route('key');
            if (!$key) {
                return $this->handleApp($request, $next);
            }

            if ($key == 'connect') {
                WebhookSignature::verifyHeader(
                    $request->getContent(),
                    $request->header('Stripe-Signature'),
                    $this->config->get('services.stripe.connect_secret'),
                    $this->config->get('services.stripe.webhook.tolerance')
                );
            } else {
                $integration = get_integration($key, 'stripe');

                if ($integration) {
                    $secret = array_get($integration, 'details.secret');
                    if (!$secret) {
                        return $next($request);
                    }
                    WebhookSignature::verifyHeader(
                        $request->getContent(),
                        $request->header('Stripe-Signature'),
                        $secret,
                        $this->config->get('services.stripe.webhook.tolerance')
                    );
                } else {
                    logger()->error("Got stripe webhook but no integration present", [$key]);
                    $this->app->abort(403);
                }
            }
        } catch (SignatureVerification $exception) {
            logger()->error("stripe webhook signature verify failed", [
                $exception->getMessage(),
            ]);
            $this->app->abort(403);
        }

        return $next($request);
    }

    /**
     * Handle the incoming request for our app.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handleApp($request, Closure $next)
    {
        try {
            WebhookSignature::verifyHeader(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                $this->config->get('services.stripe.webhook_secret'),
                $this->config->get('services.stripe.webhook.tolerance')
            );
        } catch (SignatureVerification $exception) {
            logger()->error("app stripe webhook signature verify failed", [
                $exception->getMessage(),
            ]);
            $this->app->abort(403);
        }
        return $next($request);
    }
}
