<?php

namespace Tests\Feature\Certificates;

use App\Livewire\CertificatesIndex;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CertificatesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_render_the_certificates_index(): void
    {
        Livewire::test(CertificatesIndex::class)
            ->assertForbidden();
    }

    public function test_search_filters_own_certificates_reactively(): void
    {
        $user = User::factory()->create();
        Certificate::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Alice Wonderland',
        ]);
        Certificate::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Bob Builder',
        ]);

        Livewire::actingAs($user)
            ->test(CertificatesIndex::class)
            ->set('search', 'Alice')
            ->assertSee('Alice Wonderland')
            ->assertDontSee('Bob Builder');
    }

    public function test_does_not_show_other_users_certificates(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Certificate::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Mine Only',
        ]);
        Certificate::factory()->create([
            'user_id' => $other->id,
            'recipient_name' => 'Someone Else',
        ]);

        Livewire::actingAs($user)
            ->test(CertificatesIndex::class)
            ->assertSee('Mine Only')
            ->assertDontSee('Someone Else');
    }

    public function test_status_tab_filters_expired_certificates(): void
    {
        $user = User::factory()->create();
        Certificate::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Still Active',
            'date_of_expiry' => null,
        ]);
        Certificate::factory()->expired()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Already Expired',
        ]);

        Livewire::actingAs($user)
            ->test(CertificatesIndex::class)
            ->call('setStatus', 'expired')
            ->assertSee('Already Expired')
            ->assertDontSee('Still Active');
    }

    public function test_query_string_search_hydrates_on_mount(): void
    {
        $user = User::factory()->create();
        Certificate::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Alice Wonderland',
        ]);
        Certificate::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Bob Builder',
        ]);

        $this->actingAs($user)
            ->get(route('certificates.index', ['search' => 'Alice']))
            ->assertOk()
            ->assertSee('Alice Wonderland')
            ->assertDontSee('Bob Builder');
    }
}
