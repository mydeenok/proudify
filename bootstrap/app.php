<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsApproved;
use App\Http\Middleware\RedirectAdminFromTenantRoutes;
use App\Jobs\Billing\ExpireStaleCertificateOrdersJob;
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
        // Subscription-based billing is retired (see CertificateOrder /
        // CertificatePricingService for the pay-per-certificate model that
        // replaced it) - the job classes stay in the codebase for now
        // (see app/Jobs/Subscriptions) but are no longer scheduled.
        $schedule->job(new ExpireStaleCertificateOrdersJob)->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
