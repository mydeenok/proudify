<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    private function validPlanData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Starter',
            'certificates_per_month' => 10,
            'certificates_per_year' => 120,
            'users_per_month' => 10,
            'users_per_year' => 120,
            'cost_month_inr' => 0,
            'cost_year_inr' => 0,
            'cost_month_usd' => 0,
            'cost_year_usd' => 0,
        ], $overrides);
    }

    public function test_only_admins_can_manage_plans(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.subscriptions.index'))
            ->assertForbidden();
    }

    public function test_marking_a_new_plan_as_the_default_free_plan_unsets_any_existing_one(): void
    {
        $admin = User::factory()->admin()->create();
        $existingFreePlan = Subscription::factory()->free()->create();

        $this->actingAs($admin)->post(route('admin.subscriptions.store'), $this->validPlanData([
            'name' => 'New Free',
            'is_default_free_plan' => '1',
        ]));

        $this->assertFalse($existingFreePlan->fresh()->is_default_free_plan);
        $this->assertDatabaseHas('subscriptions', ['name' => 'New Free', 'is_default_free_plan' => true]);
    }

    public function test_updating_a_plan_to_be_the_default_free_plan_unsets_others(): void
    {
        $admin = User::factory()->admin()->create();
        $existingFreePlan = Subscription::factory()->free()->create();
        $otherPlan = Subscription::factory()->create();

        $this->actingAs($admin)->put(route('admin.subscriptions.update', $otherPlan), $this->validPlanData([
            'name' => $otherPlan->name,
            'is_default_free_plan' => '1',
        ]));

        $this->assertFalse($existingFreePlan->fresh()->is_default_free_plan);
        $this->assertTrue($otherPlan->fresh()->is_default_free_plan);
    }

    public function test_toggling_status_flips_is_active(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Subscription::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.subscriptions.toggle-status', $plan));

        $this->assertFalse($plan->fresh()->is_active);
    }
}
