<?php

namespace App\Jobs\Subscriptions;

use App\Models\UserSubscription;
use App\Notifications\AdminSubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiredNotification;
use App\Support\NotifyAdmins;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireStaleSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        UserSubscription::query()
            ->where('is_active', true)
            ->where('auto_renew', false)
            ->where('end_date', '<', now())
            ->with('user', 'subscription')
            ->each(function (UserSubscription $userSubscription) {
                $userSubscription->update(['is_active' => false]);
                $userSubscription->user->notify(new SubscriptionExpiredNotification($userSubscription));

                NotifyAdmins::notify(
                    new AdminSubscriptionExpiredNotification($userSubscription),
                    'Failed to send admin subscription-expired alert.',
                    ['user_subscription_id' => $userSubscription->id],
                );
            });
    }
}
