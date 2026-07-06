<?php

namespace App\Jobs\Subscriptions;

use App\Models\UserSubscription;
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
            ->update(['is_active' => false]);
    }
}
