<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AnalyticsDashboard;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateOrder;
use App\Models\CertificateVerification;
use App\Models\Subscription;
use App\Models\Template;
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

    /**
     * Revenue now comes from two sources - legacy subscriptions and the
     * pay-per-certificate CertificateOrder model - and they must be added
     * together in the same INR bucket, not shown as two separate figures.
     */
    public function test_analytics_reflects_pay_per_certificate_revenue_alongside_subscriptions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $template = Template::factory()->create();
        $plan = Subscription::factory()->create();

        UserSubscription::factory()->for($plan)->create(['payment_status' => 'completed', 'amount_paid' => 100, 'currency' => 'INR']);

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

        // Pending/failed orders must not be counted as revenue.
        CertificateOrder::create([
            'user_id' => $user->id,
            'type' => 'single',
            'template_id' => $template->id,
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'total_amount' => 20,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertSee('₹120.00', false);
    }

    /**
     * Replaces the old "Users by Plan" donut - subscriptions no longer
     * gate anyone, so grouping by plan would show a meaningless single
     * segment for nearly every tenant now.
     */
    public function test_analytics_shows_issuance_by_type_instead_of_users_by_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $template = Template::factory()->create();
        $batch = CertificateBatch::factory()->create(['user_id' => $user->id, 'template_id' => $template->id]);

        Certificate::factory()->count(3)->create(['user_id' => $user->id, 'certificate_batch_id' => null]);
        Certificate::factory()->count(2)->create(['user_id' => $user->id, 'certificate_batch_id' => $batch->id]);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertSee('Issuance by Type');
        $response->assertDontSee('Users by Plan');
        $response->assertSee('Single');
        $response->assertSee('Bulk');
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
