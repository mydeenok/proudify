<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AnalyticsDashboard;
use App\Models\Certificate;
use App\Models\CertificateVerification;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_view_analytics(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.analytics.index'))
            ->assertForbidden();
    }

    public function test_analytics_reports_revenue_by_currency(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Subscription::factory()->create();

        UserSubscription::factory()->for($plan)->create(['payment_status' => 'completed', 'amount_paid' => 999, 'currency' => 'INR']);
        UserSubscription::factory()->for($plan)->create(['payment_status' => 'completed', 'amount_paid' => 29, 'currency' => 'USD']);
        UserSubscription::factory()->for($plan)->create(['payment_status' => 'failed', 'amount_paid' => 999, 'currency' => 'INR']);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertSee('₹999.00', false);
        $response->assertSee('$29.00', false);
    }

    public function test_analytics_reports_verification_rate(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = Certificate::factory()->create();

        CertificateVerification::insert(array_fill(0, 3, [
            'certificate_id' => $certificate->id, 'result' => 'valid', 'created_at' => now(), 'updated_at' => now(),
        ]));
        CertificateVerification::create(['certificate_id' => $certificate->id, 'result' => 'not_found']);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertSee('75%');
    }

    public function test_period_filter_hides_older_metrics(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Subscription::factory()->create();

        UserSubscription::factory()->for($plan)->create([
            'payment_status' => 'completed',
            'amount_paid' => 500,
            'currency' => 'INR',
            'created_at' => now()->subDays(45),
        ]);
        UserSubscription::factory()->for($plan)->create([
            'payment_status' => 'completed',
            'amount_paid' => 100,
            'currency' => 'INR',
            'created_at' => now()->subDays(2),
        ]);

        Livewire::actingAs($admin)
            ->test(AnalyticsDashboard::class)
            ->set('period', 7)
            ->assertSee('₹100.00', false)
            ->assertDontSee('₹500.00', false)
            ->assertSee('Last 7 Days');
    }
}
