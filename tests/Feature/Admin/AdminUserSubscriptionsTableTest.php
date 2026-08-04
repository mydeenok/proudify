<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UserSubscriptionsTable;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserSubscriptionsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_render_the_user_subscriptions_table(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UserSubscriptionsTable::class)
            ->assertForbidden();
    }

    public function test_search_filters_subscriptions_by_organization(): void
    {
        $admin = User::factory()->admin()->create();
        $acme = User::factory()->create(['organization_name' => 'Acme University', 'email' => 'acme@example.com']);
        $zen = User::factory()->create(['organization_name' => 'Zen Corp', 'email' => 'zen@example.com']);
        UserSubscription::factory()->create(['user_id' => $acme->id]);
        UserSubscription::factory()->create(['user_id' => $zen->id]);

        Livewire::actingAs($admin)
            ->test(UserSubscriptionsTable::class)
            ->set('search', 'Acme')
            ->assertSee('Acme University')
            ->assertDontSee('Zen Corp');
    }

    public function test_query_string_search_hydrates_on_mount(): void
    {
        $admin = User::factory()->admin()->create();
        $acme = User::factory()->create(['organization_name' => 'Acme University']);
        $zen = User::factory()->create(['organization_name' => 'Zen Corp']);
        UserSubscription::factory()->create(['user_id' => $acme->id]);
        UserSubscription::factory()->create(['user_id' => $zen->id]);

        $this->actingAs($admin)
            ->get(route('admin.user-subscriptions.index', ['search' => 'Acme']))
            ->assertOk()
            ->assertSee('Acme University')
            ->assertDontSee('Zen Corp');
    }
}
