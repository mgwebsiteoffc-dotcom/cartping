<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global state: resolve the authenticated merchant's store context.
        $middleware->append(\App\Http\Middleware\ResolveStoreContext::class);

        // The embedded session-token exchange is authenticated by the JWT
        // signature itself, and cookies (hence CSRF tokens) are blocked inside
        // the Shopify admin iframe — so exempt it from CSRF verification.
        $middleware->validateCsrfTokens(except: ['auth/shopify/session']);

        // Auth guard aliases used by controllers.
        $middleware->alias([
            'auth.store'    => \App\Http\Middleware\AuthenticateStore::class,
            'shopify.webhook' => \App\Http\Middleware\VerifyShopifyWebhook::class,
            'shopify.session' => \App\Http\Middleware\VerifyShopifySession::class,
            'whatsapp.webhook' => \App\Http\Middleware\VerifyWhatsappWebhook::class,
            'provisioned'   => \App\Http\Middleware\RequireProvisioned::class,
            'role:owner'    => \App\Http\Middleware\EnsureOwnerRole::class,
            'owner'         => \App\Http\Middleware\EnsurePlatformOwner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Trusted proxies (embedded Shopify admin uses the app inside an iframe).
        $exceptions->dontReport(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);

        $exceptions->render(function (Throwable $e, Request $request) {
            return null; // fall through to default rendering
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        // Re-evaluate time-based automation (abandoned cart recovery delays,
        // entry/exit popups, and enrichment) each minute.
        $schedule->command('cartping:automations --run')->everyMinute()->withoutOverlapping();

        // Dispatch due scheduled campaigns + resume pending flow runs.
        $schedule->command('cartping:campaigns --dispatch')->everyMinute()->withoutOverlapping();

        // Aggregate raw events into rollups for dashboards.
        $schedule->command('cartping:metrics:rollup --period=hourly')->hourly();

        // Prune old queued-failed rows / raw events per retention policy.
        $schedule->command('cartping:prune')->daily();
    })
    ->create();
