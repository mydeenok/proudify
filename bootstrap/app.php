<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsApproved;
use App\Http\Middleware\RedirectAdminFromTenantRoutes;
use App\Jobs\Subscriptions\ExpireStaleSubscriptionsJob;
use App\Jobs\Subscriptions\NotifyExpiringSubscriptionsJob;
use App\Jobs\Subscriptions\ResetUsageCountersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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

        // Razorpay's webhook POST carries its own HMAC signature (verified
        // in RazorpayWebhookController) instead of a Laravel session CSRF
        // token, since it isn't a browser-originated request.
        $middleware->validateCsrfTokens(except: [
            'webhooks/razorpay',
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->job(new ResetUsageCountersJob)->hourly();
        $schedule->job(new NotifyExpiringSubscriptionsJob)->daily();
        $schedule->job(new ExpireStaleSubscriptionsJob)->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
