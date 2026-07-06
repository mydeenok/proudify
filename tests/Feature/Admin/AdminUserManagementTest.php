<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_view_the_user_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_search_filters_users_by_name_or_organization(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['first_name' => 'Priya', 'organization_name' => 'Acme University']);
        User::factory()->create(['first_name' => 'Kevin', 'organization_name' => 'Zen Corp']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Acme']));

        $response->assertOk();
        $response->assertSee('Priya');
        $response->assertDontSee('Kevin');
    }

    public function test_an_admin_can_suspend_an_active_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('admin.users.suspend', $user))
            ->assertRedirect();

        $this->assertSame('suspended', $user->fresh()->status);
    }

    public function test_a_suspended_user_is_logged_out_and_blocked_on_next_request(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['status' => 'suspended']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_an_admin_can_reactivate_a_suspended_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['status' => 'suspended']);

        $this->actingAs($admin)
            ->patch(route('admin.users.reactivate', $user))
            ->assertRedirect();

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_admins_cannot_be_suspended(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.suspend', $otherAdmin))
            ->assertForbidden();
    }
}
