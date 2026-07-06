<?php

namespace App\Jobs\Subscriptions;

use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Resets certificates_used/users_used only for subscriptions whose current
 * billing period has actually elapsed AND that auto-renew — the reference
 * app never reset these counters at all. Paid, non-auto-renewing plans are
 * deliberately left alone here; they expire via ExpireStaleSubscriptionsJob
 * and the user renews through a fresh checkout, matching this project's
 * explicit choice not to implement recurring auto-charging.
 */
class ResetUsageCountersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        UserSubscription::query()
            ->where('is_active', true)
            ->where('auto_renew', true)
            ->where('payment_status', 'completed')
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $periodLength = $subscription->billing_period === 'yearly' ? '1 year' : '1 month';
                    $periodStart = $subscription->current_period_started_at->copy();

                    // Loop rather than advance once, so a subscription that's
                    // fallen multiple periods behind (e.g. the scheduler was
                    // paused) fully catches up in a single run instead of
                    // needing one job execution per missed period.
                    $advanced = false;
                    while ($periodStart->copy()->modify("+{$periodLength}")->isPast()) {
                        $periodStart->modify("+{$periodLength}");
                        $advanced = true;
                    }

                    if ($advanced) {
                        $subscription->forceFill([
                            'certificates_used' => 0,
                            'users_used' => 0,
                            'current_period_started_at' => $periodStart,
                            'end_date' => $periodStart->copy()->modify("+{$periodLength}"),
                        ])->save();
                    }
                }
            });
    }
}
