<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\SubscriptionPlansTable;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSubscriptionPlansTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_render_plans_table(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SubscriptionPlansTable::class)
            ->assertForbidden();
    }

    public function test_search_and_status_filters_plans_reactively(): void
    {
        $admin = User::factory()->admin()->create();
        Subscription::factory()->create(['name' => 'Starter', 'is_active' => true]);
        Subscription::factory()->create(['name' => 'Enterprise', 'is_active' => false, 'cost_month_inr' => 999, 'cost_month_usd' => 29]);

        Livewire::actingAs($admin)
            ->test(SubscriptionPlansTable::class)
            ->set('search', 'Starter')
            ->assertSee('Starter')
            ->assertDontSee('Enterprise')
            ->set('search', '')
            ->set('status', 'inactive')
            ->assertSee('Enterprise')
            ->assertDontSee('Starter');
    }

    public function test_type_filter_separates_free_and_paid(): void
    {
        $admin = User::factory()->admin()->create();
        Subscription::factory()->create([
            'name' => 'Freebie',
            'cost_month_inr' => 0,
            'cost_month_usd' => 0,
        ]);
        Subscription::factory()->create([
            'name' => 'Pro Plan',
            'cost_month_inr' => 499,
            'cost_month_usd' => 19,
        ]);

        Livewire::actingAs($admin)
            ->test(SubscriptionPlansTable::class)
            ->set('type', 'paid')
            ->assertSee('Pro Plan')
            ->assertDontSee('Freebie');
    }
}
