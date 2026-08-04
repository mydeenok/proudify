<?php

namespace Tests\Feature;

use App\Jobs\Bulk\FinalizeCertificateBatchJob;
use App\Jobs\Subscriptions\ExpireStaleSubscriptionsJob;
use App\Models\CertificateBatch;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\AdminBulkUploadCompletedNotification;
use App\Notifications\AdminPaymentFailedNotification;
use App\Notifications\AdminQuotaAlmostFullNotification;
use App\Notifications\AdminSubscriptionCancelledNotification;
use App\Notifications\AdminSubscriptionExpiredNotification;
use App\Services\SubscriptionQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminMandatoryNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_are_notified_when_a_bulk_batch_finalizes(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $batch = CertificateBatch::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'total_rows' => 2,
            'succeeded_rows' => 2,
            'failed_rows' => 0,
        ]);

        (new FinalizeCertificateBatchJob($batch->id))->handle();

        Notification::assertSentTo($admin, AdminBulkUploadCompletedNotification::class);
    }

    public function test_admins_are_notified_when_a_subscription_is_cancelled(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $subscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.user-subscriptions.cancel', $subscription))
            ->assertRedirect();

        Notification::assertSentTo($admin, AdminSubscriptionCancelledNotification::class);
    }

    public function test_admins_are_notified_when_subscriptions_expire(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'auto_renew' => false,
            'end_date' => now()->subDay(),
        ]);

        (new ExpireStaleSubscriptionsJob)->handle();

        Notification::assertSentTo($admin, AdminSubscriptionExpiredNotification::class);
    }

    public function test_admins_are_notified_when_quota_crosses_the_warning_threshold(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $subscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'payment_status' => 'completed',
            'certificates_limit' => 10,
            'certificates_used' => 8,
            'users_limit' => 100,
            'users_used' => 1,
        ]);

        // Crossing from 80% to 90% should fire the warning once.
        app(SubscriptionQuotaService::class)->consume($subscription, isNewRecipient: true);

        Notification::assertSentTo($admin, AdminQuotaAlmostFullNotification::class);
        $this->assertSame(9, $subscription->fresh()->certificates_used);
    }

    public function test_admins_are_notified_when_payment_fails_via_webhook(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $subscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'razorpay_payment_id' => 'pay_test_failed_1',
            'payment_status' => 'pending',
            'is_active' => true,
        ]);

        $this->partialMock(\App\Services\RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
        });

        $this->postJson(route('webhooks.razorpay'), [
            'event' => 'payment.failed',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_test_failed_1',
                    ],
                ],
            ],
        ])->assertOk();

        Notification::assertSentTo($admin, AdminPaymentFailedNotification::class);
        $this->assertSame('failed', $subscription->fresh()->payment_status);
    }
}
