<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Subscription-tier billing is retired (see CertificatePricingService /
 * CertificateOrder) - these pages redirect instead of rendering stale
 * subscription content, without deleting the underlying routes/views.
 *
 * purchase.* joined this list because it was still taking live Razorpay
 * payments for a plan SubscriptionQuotaService never actually enforces
 * anywhere in certificate/bulk issuance - a tenant could genuinely pay for
 * a subscription that granted nothing. See tests/Feature/Subscriptions/PurchaseTest.php's
 * git history for the old live-payment-flow coverage this replaced.
 */
class RetiredSubscriptionPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_page_redirects_a_guest_home(): void
    {
        $this->get(route('pricing'))->assertRedirect(url('/'));
    }

    public function test_pricing_page_redirects_a_tenant_to_their_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('pricing'))->assertRedirect(route('dashboard'));
    }

    public function test_subscriptions_index_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('subscriptions.index'))->assertRedirect(route('dashboard'));
    }

    public function test_purchase_show_redirects_to_dashboard_instead_of_taking_payment(): void
    {
        $user = User::factory()->create();
        $plan = Subscription::factory()->create();

        $this->actingAs($user)->get(route('purchase.show', $plan))->assertRedirect(route('dashboard'));
    }

    public function test_purchase_process_redirects_instead_of_activating_a_plan(): void
    {
        $user = User::factory()->create();
        $plan = Subscription::factory()->free()->create();

        $this->actingAs($user)->post(route('purchase.process', $plan))->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $user->id]);
    }

    public function test_purchase_create_order_no_longer_talks_to_razorpay(): void
    {
        $user = User::factory()->create();
        $plan = Subscription::factory()->create();

        $this->actingAs($user)
            ->postJson(route('purchase.create-order', $plan))
            ->assertStatus(410);
    }

    public function test_purchase_verify_payment_no_longer_creates_a_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Subscription::factory()->create();

        $this->actingAs($user)
            ->postJson(route('purchase.verify-payment', $plan), [
                'razorpay_order_id' => 'order_x',
                'razorpay_payment_id' => 'pay_x',
                'razorpay_signature' => 'sig_x',
                'period' => 'monthly',
            ])
            ->assertStatus(410);

        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $user->id]);
    }
}
