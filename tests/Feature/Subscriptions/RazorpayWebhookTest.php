<?php

namespace Tests\Feature\Subscriptions;

use App\Jobs\Certificates\SendCertificateIssuedEmailJob;
use App\Models\Certificate;
use App\Models\CertificateOrder;
use App\Models\Template;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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

    /**
     * Safety net for a browser tab closing right after payment succeeds
     * but before the client-side verifyPayment() call completes - the
     * webhook is the only other thing that ever completes a
     * CertificateOrder.
     */
    public function test_a_captured_payment_webhook_completes_a_pending_certificate_order(): void
    {
        Bus::fake();
        $real = $this->app->make(\App\Services\CertificateRenderService::class);
        $mock = \Mockery::mock($real)->makePartial();
        $mock->shouldReceive('generateAssetsSynchronously')->andReturnUsing(function (Certificate $certificate) {
            $certificate->forceFill(['image_generation_status' => 'completed'])->save();
        });
        $this->app->instance(\App\Services\CertificateRenderService::class, $mock);

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
        });

        $user = User::factory()->create();
        $template = Template::factory()->create();
        $order = CertificateOrder::create([
            'user_id' => $user->id,
            'type' => 'single',
            'template_id' => $template->id,
            'payload' => [
                'title' => 'Certificate',
                'recipient_name' => 'Jane Doe',
                'recipient_email' => 'jane@example.com',
                'date_of_issue' => now()->toDateString(),
            ],
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'total_amount' => 20,
            'status' => 'pending',
            'razorpay_order_id' => 'order_webhook_test',
        ]);

        $this->postJson(route('webhooks.razorpay'), [
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => ['id' => 'pay_webhook_test', 'order_id' => 'order_webhook_test']]],
        ])->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->certificate_id);
        Bus::assertDispatched(SendCertificateIssuedEmailJob::class);
    }

    public function test_a_failed_payment_webhook_marks_a_certificate_order_failed(): void
    {
        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
        });

        $user = User::factory()->create();
        $template = Template::factory()->create();
        $order = CertificateOrder::create([
            'user_id' => $user->id,
            'type' => 'single',
            'template_id' => $template->id,
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'total_amount' => 20,
            'status' => 'pending',
            'razorpay_payment_id' => 'pay_fail_test',
        ]);

        $this->postJson(route('webhooks.razorpay'), [
            'event' => 'payment.failed',
            'payload' => ['payment' => ['entity' => ['id' => 'pay_fail_test']]],
        ])->assertOk();

        $this->assertSame('failed', $order->fresh()->status);
    }
}
