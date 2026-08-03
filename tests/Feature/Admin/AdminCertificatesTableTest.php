<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CertificatesTable;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCertificatesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_render_the_certificates_table(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CertificatesTable::class)
            ->assertForbidden();
    }

    public function test_search_filters_certificates_reactively(): void
    {
        $admin = User::factory()->admin()->create();
        Certificate::factory()->create(['recipient_name' => 'Alice Wonderland']);
        Certificate::factory()->create(['recipient_name' => 'Bob Builder']);

        Livewire::actingAs($admin)
            ->test(CertificatesTable::class)
            ->set('search', 'Alice')
            ->assertSee('Alice Wonderland')
            ->assertDontSee('Bob Builder');
    }

    public function test_query_string_search_hydrates_on_mount(): void
    {
        $admin = User::factory()->admin()->create();
        Certificate::factory()->create(['recipient_name' => 'Alice Wonderland']);
        Certificate::factory()->create(['recipient_name' => 'Bob Builder']);

        $this->actingAs($admin)
            ->get(route('admin.certificates.index', ['search' => 'Alice']))
            ->assertOk()
            ->assertSee('Alice Wonderland')
            ->assertDontSee('Bob Builder');
    }
}
