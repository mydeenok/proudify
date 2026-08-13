<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsApproved;
use App\Http\Middleware\RedirectAdminFromTenantRoutes;
use App\Jobs\Billing\ExpireStaleCertificateOrdersJob;
use App\Jobs\Subscriptions\ExpireStaleSubscriptionsJob;
use App\Jobs\Subscriptions\ResetUsageCountersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'approved' => EnsureUserIsApproved::class,
            'admin' => EnsureUserIsAdmin::class,
            'tenant-only' => RedirectAdminFromTenantRoutes::class,
        ]);

        // Required for the "log out other devices" security feature to
        // actually take effect: Auth::logoutOtherDevices() only rehashes
        // the current session's password hash - it's this middleware that
        // checks that hash on every request and is what actually
        // invalidates a stale session on its next request.
        $middleware->web(append: [
            AuthenticateSession::class,
        ]);

        // Razorpay's webhook POST carries its own HMAC signature (verified
        // in RazorpayWebhookController) instead of a Laravel session CSRF
        // token, since it isn't a browser-originated request.
        $middleware->validateCsrfTokens(except: [
            'webhooks/razorpay',
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->job(new ExpireStaleCertificateOrdersJob)->hourly();

        // Subscription-based billing is retired (PurchaseController now
        // redirects instead of taking payment - see CertificateOrder /
        // CertificatePricingService for the pay-per-certificate model that
        // replaced it), so no new UserSubscription rows get created. These
        // still need to run for whatever legacy rows already exist:
        // without them, an old subscription's end_date passing would never
        // flip is_active off, leaving stale "active" data around
        // indefinitely for anything that still reads it.
        $schedule->job(new ExpireStaleSubscriptionsJob)->daily();
        $schedule->job(new ResetUsageCountersJob)->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
