<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\AdminNewPurchaseNotification;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_activate_the_free_plan_once(): void
    {
        $user = User::factory()->create();
        $freePlan = Subscription::factory()->free()->create();

        $this->actingAs($user)
            ->post(route('purchase.process', $freePlan))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_id' => $freePlan->id,
            'payment_status' => 'completed',
        ]);
    }

    public function test_a_user_cannot_claim_the_free_plan_twice(): void
    {
        $user = User::factory()->create();
        $freePlan = Subscription::factory()->free()->create();

        $this->actingAs($user)->post(route('purchase.process', $freePlan));

        $this->actingAs($user)
            ->post(route('purchase.process', $freePlan))
            ->assertSessionHasErrors('plan');

        $this->assertSame(1, UserSubscription::where('user_id', $user->id)->count());
    }

    public function test_a_paid_plan_cannot_be_activated_through_the_free_process_endpoint(): void
    {
        $user = User::factory()->create();
        $paidPlan = Subscription::factory()->create();

        $this->actingAs($user)
            ->post(route('purchase.process', $paidPlan))
            ->assertSessionHasErrors('plan');

        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $user->id]);
    }

    public function test_creating_an_order_computes_amount_server_side_and_never_trusts_the_client(): void
    {
        $user = User::factory()->create();
        $paidPlan = Subscription::factory()->create(['cost_month_inr' => 999]);

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('createOrder')
                ->once()
                ->withArgs(fn ($amount, $currency) => $amount === 999.0 && $currency === 'INR')
                ->andReturn(['id' => 'order_test123', 'amount' => 99900, 'currency' => 'INR']);
        });

        $response = $this->actingAs($user)->postJson(route('purchase.create-order', $paidPlan).'?period=monthly');

        $response->assertOk()->assertJson(['id' => 'order_test123']);
    }

    public function test_creating_an_order_fails_gracefully_when_razorpay_rejects_the_request(): void
    {
        $user = User::factory()->create();
        $paidPlan = Subscription::factory()->create(['cost_month_inr' => 999]);

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('createOrder')
                ->once()
                ->andThrow(new \Razorpay\Api\Errors\BadRequestError('Authentication failed', 401, 401));
        });

        $response = $this->actingAs($user)->postJson(route('purchase.create-order', $paidPlan).'?period=monthly');

        $response->assertStatus(502)->assertJsonStructure(['message']);
        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $user->id]);
    }

    public function test_verify_payment_rejects_an_invalid_signature(): void
    {
        $user = User::factory()->create();
        $paidPlan = Subscription::factory()->create();

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifySignature')->once()->andReturn(false);
        });

        $response = $this->actingAs($user)->postJson(route('purchase.verify-payment', $paidPlan), [
            'razorpay_order_id' => 'order_x',
            'razorpay_payment_id' => 'pay_x',
            'razorpay_signature' => 'forged',
            'period' => 'monthly',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $user->id]);
    }

    public function test_verify_payment_is_idempotent_on_replayed_payment_id(): void
    {
        $user = User::factory()->create();
        $paidPlan = Subscription::factory()->create();

        UserSubscription::factory()->for($user)->for($paidPlan)->create([
            'razorpay_payment_id' => 'pay_already_processed',
        ]);

        $response = $this->actingAs($user)->postJson(route('purchase.verify-payment', $paidPlan), [
            'razorpay_order_id' => 'order_x',
            'razorpay_payment_id' => 'pay_already_processed',
            'razorpay_signature' => 'whatever',
            'period' => 'monthly',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, UserSubscription::where('razorpay_payment_id', 'pay_already_processed')->count());
    }

    public function test_verify_payment_creates_a_completed_subscription_on_valid_signature(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $paidPlan = Subscription::factory()->create(['cost_month_inr' => 999]);

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifySignature')->once()->andReturn(true);
        });

        $response = $this->actingAs($user)->postJson(route('purchase.verify-payment', $paidPlan), [
            'razorpay_order_id' => 'order_x',
            'razorpay_payment_id' => 'pay_y',
            'razorpay_signature' => 'valid_signature',
            'period' => 'monthly',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'razorpay_payment_id' => 'pay_y',
            'payment_status' => 'completed',
        ]);
        Notification::assertSentTo($admin, AdminNewPurchaseNotification::class);
    }
}
