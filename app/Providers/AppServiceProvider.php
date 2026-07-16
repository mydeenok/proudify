<?php

namespace App\Providers;

use App\Listeners\LogEmailDelivery;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Caps concurrent PDF->JPG conversions (Imagick/Ghostscript is
        // memory-heavy) so a burst of certificate issuance can't exhaust
        // the certificates-queue worker's resources.
        RateLimiter::for('pdf-conversions', fn () => Limit::perMinute(30));

        Event::listen(NotificationSent::class, [LogEmailDelivery::class, 'handleSent']);
        Event::listen(NotificationFailed::class, [LogEmailDelivery::class, 'handleFailed']);
    }
}
