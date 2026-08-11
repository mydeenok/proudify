<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateOrder;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_certificates_and_spend_for_this_month(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create();

        Certificate::factory()->create(['user_id' => $user->id, 'created_at' => now()]);
        Certificate::factory()->create(['user_id' => $user->id, 'created_at' => now()->subMonths(2)]);

        CertificateOrder::create([
            'user_id' => $user->id,
            'type' => 'single',
            'template_id' => $template->id,
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'total_amount' => 20,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('This Month');
        $response->assertSee('₹20.00', false);
        $response->assertSee('Last Order');
        $response->assertDontSee('Quota Remaining');
        $response->assertDontSee('Current Plan');
    }

    public function test_dashboard_shows_empty_state_with_no_orders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No orders yet');
    }
}
