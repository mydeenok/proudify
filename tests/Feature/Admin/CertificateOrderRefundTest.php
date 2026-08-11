<?php

namespace Tests\Feature\Admin;

use App\Models\CertificateOrder;
use App\Models\Template;
use App\Models\User;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateOrderRefundTest extends TestCase
{
    use RefreshDatabase;

    private function makePaidOrder(User $user, Template $template): CertificateOrder
    {
        return CertificateOrder::create([
            'user_id' => $user->id,
            'type' => 'single',
            'template_id' => $template->id,
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'total_amount' => 20,
            'status' => 'paid',
            'razorpay_payment_id' => 'pay_refund_test',
            'paid_at' => now(),
        ]);
    }

    public function test_only_admins_can_view_certificate_orders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.certificate-orders.index'))->assertForbidden();
    }

    public function test_an_admin_can_refund_a_paid_order(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $template = Template::factory()->create();
        $order = $this->makePaidOrder($user, $template);

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('refund')
                ->once()
                ->withArgs(fn ($paymentId, $amount) => $paymentId === 'pay_refund_test' && $amount === 20.0)
                ->andReturn(['id' => 'rfnd_test123', 'status' => 'processed']);
        });

        $response = $this->actingAs($admin)->post(route('admin.certificate-orders.refund', $order));

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame('refunded', $order->status);
        $this->assertSame('rfnd_test123', $order->razorpay_refund_id);
        $this->assertSame($admin->id, $order->refunded_by);
        $this->assertNotNull($order->refunded_at);
    }

    public function test_a_non_admin_cannot_refund_an_order(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create();
        $order = $this->makePaidOrder($user, $template);

        $this->actingAs($user)->post(route('admin.certificate-orders.refund', $order))->assertForbidden();

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_a_pending_order_cannot_be_refunded(): void
    {
        $admin = User::factory()->admin()->create();
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
        ]);

        $this->actingAs($admin)->post(route('admin.certificate-orders.refund', $order))->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_refund_failure_leaves_the_order_paid_with_an_error_message(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $template = Template::factory()->create();
        $order = $this->makePaidOrder($user, $template);

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('refund')
                ->once()
                ->andThrow(new \Razorpay\Api\Errors\BadRequestError('Refund not possible', 400, 400));
        });

        $response = $this->actingAs($admin)->post(route('admin.certificate-orders.refund', $order));

        $response->assertRedirect();
        $response->assertSessionHas('status', fn ($status) => str_contains($status, 'Refund failed'));
        $this->assertSame('paid', $order->fresh()->status);
    }
}
