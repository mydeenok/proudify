<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UsersTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUsersTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_render_the_users_table(): void
    {
        Livewire::test(UsersTable::class)
            ->assertForbidden();
    }

    public function test_non_admins_cannot_render_the_users_table(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UsersTable::class)
            ->assertForbidden();
    }

    public function test_search_filters_users_reactively(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['first_name' => 'Priya', 'organization_name' => 'Acme University']);
        User::factory()->create(['first_name' => 'Kevin', 'organization_name' => 'Zen Corp']);

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->set('search', 'Acme')
            ->assertSee('Priya')
            ->assertDontSee('Kevin');
    }

    public function test_status_and_role_filters_combine(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create([
            'first_name' => 'ActiveUser',
            'status' => 'active',
            'role' => 'user',
        ]);
        User::factory()->create([
            'first_name' => 'SuspendedUser',
            'status' => 'suspended',
            'role' => 'user',
        ]);
        User::factory()->admin()->create([
            'first_name' => 'ActiveAdmin',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->set('status', 'active')
            ->set('role', 'user')
            ->assertSee('ActiveUser')
            ->assertDontSee('SuspendedUser')
            ->assertDontSee('ActiveAdmin');
    }

    public function test_query_string_search_hydrates_on_mount(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['first_name' => 'Priya', 'organization_name' => 'Acme University']);
        User::factory()->create(['first_name' => 'Kevin', 'organization_name' => 'Zen Corp']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Acme']))
            ->assertOk()
            ->assertSee('Priya')
            ->assertDontSee('Kevin');
    }
}
