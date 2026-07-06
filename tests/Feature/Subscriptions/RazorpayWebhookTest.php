<?php

namespace Tests\Feature\Subscriptions;

use App\Models\UserSubscription;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_payment_webhook_marks_the_subscription_failed(): void
    {
        $subscription = UserSubscription::factory()->create([
            'razorpay_payment_id' => 'pay_123',
            'payment_status' => 'completed',
            'is_active' => true,
        ]);

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
        });

        $this->postJson(route('webhooks.razorpay'), [
            'event' => 'payment.failed',
            'payload' => ['payment' => ['entity' => ['id' => 'pay_123']]],
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame('failed', $subscription->payment_status);
        $this->assertFalse($subscription->is_active);
    }

    public function test_an_invalid_webhook_signature_is_rejected(): void
    {
        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(false);
        });

        $this->postJson(route('webhooks.razorpay'), [
            'event' => 'payment.failed',
            'payload' => ['payment' => ['entity' => ['id' => 'pay_123']]],
        ])->assertStatus(400);
    }

    public function test_an_unknown_payment_id_is_acknowledged_without_error(): void
    {
        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
        });

        $this->postJson(route('webhooks.razorpay'), [
            'event' => 'payment.failed',
            'payload' => ['payment' => ['entity' => ['id' => 'pay_does_not_exist']]],
        ])->assertOk();
    }
}
