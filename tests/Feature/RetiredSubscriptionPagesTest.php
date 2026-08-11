<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Subscription-tier billing is retired (see CertificatePricingService /
 * CertificateOrder) - these pages redirect instead of rendering stale
 * subscription content, without deleting the underlying routes/views.
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
}
