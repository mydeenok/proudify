<?php

namespace Tests\Feature\Subscriptions;

use App\Jobs\Subscriptions\ExpireStaleSubscriptionsJob;
use App\Jobs\Subscriptions\ResetUsageCountersJob;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_job_resets_counters_for_an_auto_renewing_subscription_past_its_period(): void
    {
        $subscription = UserSubscription::factory()->create([
            'auto_renew' => true,
            'payment_status' => 'completed',
            'is_active' => true,
            'billing_period' => 'monthly',
            'current_period_started_at' => now()->subMonths(2),
            'certificates_used' => 45,
            'users_used' => 10,
        ]);

        (new ResetUsageCountersJob)->handle();

        $subscription->refresh();
        $this->assertSame(0, $subscription->certificates_used);
        $this->assertSame(0, $subscription->users_used);
        $this->assertTrue($subscription->current_period_started_at->isAfter(now()->subMonth()));
    }

    public function test_reset_job_leaves_non_auto_renewing_subscriptions_untouched(): void
    {
        $subscription = UserSubscription::factory()->create([
            'auto_renew' => false,
            'payment_status' => 'completed',
            'is_active' => true,
            'current_period_started_at' => now()->subMonths(2),
            'certificates_used' => 45,
        ]);

        (new ResetUsageCountersJob)->handle();

        $this->assertSame(45, $subscription->fresh()->certificates_used);
    }

    public function test_reset_job_leaves_subscriptions_still_within_their_period_untouched(): void
    {
        $subscription = UserSubscription::factory()->create([
            'auto_renew' => true,
            'payment_status' => 'completed',
            'is_active' => true,
            'current_period_started_at' => now()->subDays(5),
            'certificates_used' => 10,
        ]);

        (new ResetUsageCountersJob)->handle();

        $this->assertSame(10, $subscription->fresh()->certificates_used);
    }

    public function test_expire_job_deactivates_non_renewing_subscriptions_past_end_date(): void
    {
        $subscription = UserSubscription::factory()->create([
            'auto_renew' => false,
            'is_active' => true,
            'end_date' => now()->subDay(),
        ]);

        (new ExpireStaleSubscriptionsJob)->handle();

        $this->assertFalse($subscription->fresh()->is_active);
    }

    public function test_expire_job_does_not_touch_auto_renewing_subscriptions(): void
    {
        $subscription = UserSubscription::factory()->create([
            'auto_renew' => true,
            'is_active' => true,
            'end_date' => now()->subDay(),
        ]);

        (new ExpireStaleSubscriptionsJob)->handle();

        $this->assertTrue($subscription->fresh()->is_active);
    }
}
