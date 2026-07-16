<?php

namespace App\Jobs\Subscriptions;

use App\Models\UserSubscription;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Warns a subscriber before ExpireStaleSubscriptionsJob silently cuts them
 * off - only non-auto-renewing plans expire at all in this project (see
 * that job's own doc comment), so those are the only ones worth warning.
 */
class NotifyExpiringSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const WARN_DAYS_BEFORE = 3;

    public function handle(): void
    {
        UserSubscription::query()
            ->where('is_active', true)
            ->where('auto_renew', false)
            ->whereDate('end_date', now()->addDays(self::WARN_DAYS_BEFORE)->toDateString())
            ->with('user', 'subscription')
            ->each(function (UserSubscription $userSubscription) {
                $userSubscription->user->notify(
                    new SubscriptionExpiringNotification($userSubscription, self::WARN_DAYS_BEFORE)
                );
            });
    }
}
