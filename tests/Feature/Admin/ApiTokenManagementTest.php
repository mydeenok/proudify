<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ApiTokensTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_view_api_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.api-tokens.index'))->assertForbidden();
    }

    public function test_admin_sees_every_tenants_api_key(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->create(['organization_name' => 'Acme Org']);
        $tenant->createToken('CRM integration');

        $response = $this->actingAs($admin)->get(route('admin.api-tokens.index'));

        $response->assertOk();
        $response->assertSee('Acme Org');
        $response->assertSee('CRM integration');
    }

    public function test_search_filters_by_key_name_or_tenant(): void
    {
        $admin = User::factory()->admin()->create();
        $tenantA = User::factory()->create(['organization_name' => 'Acme Org']);
        $tenantB = User::factory()->create(['organization_name' => 'Other Org']);
        $tenantA->createToken('Acme key');
        $tenantB->createToken('Other key');

        Livewire::actingAs($admin)
            ->test(ApiTokensTable::class)
            ->set('search', 'Acme')
            ->assertSee('Acme key')
            ->assertDontSee('Other key');
    }

    public function test_an_admin_can_revoke_a_tenants_api_key(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->create();
        $token = $tenant->createToken('test');

        Livewire::actingAs($admin)
            ->test(ApiTokensTable::class)
            ->call('revoke', $token->accessToken->id);

        $this->assertSame(0, $tenant->tokens()->count());
    }

    public function test_a_non_admin_cannot_revoke_a_tenants_api_key(): void
    {
        $user = User::factory()->create();
        $tenant = User::factory()->create();
        $tenant->createToken('test');

        Livewire::actingAs($user)
            ->test(ApiTokensTable::class)
            ->assertForbidden();
    }
}
