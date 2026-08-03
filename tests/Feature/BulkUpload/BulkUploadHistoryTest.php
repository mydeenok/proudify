<?php

namespace Tests\Feature\BulkUpload;

use App\Livewire\BulkUploadHistory;
use App\Models\CertificateBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkUploadHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_render_bulk_upload_history(): void
    {
        Livewire::test(BulkUploadHistory::class)
            ->assertForbidden();
    }

    public function test_tenant_only_sees_own_batches(): void
    {
        $user = User::factory()->create(['organization_name' => 'Acme Org']);
        $other = User::factory()->create(['organization_name' => 'Other Org']);

        CertificateBatch::factory()->create([
            'user_id' => $user->id,
            'original_filename' => 'mine.csv',
            'status' => 'completed',
        ]);
        CertificateBatch::factory()->create([
            'user_id' => $other->id,
            'original_filename' => 'theirs.csv',
            'status' => 'completed',
        ]);

        Livewire::actingAs($user)
            ->test(BulkUploadHistory::class)
            ->assertSee('mine.csv')
            ->assertDontSee('theirs.csv');
    }

    public function test_admin_sees_all_batches_and_can_filter_by_org(): void
    {
        $admin = User::factory()->admin()->create();
        $acme = User::factory()->create(['organization_name' => 'Acme Org']);
        $other = User::factory()->create(['organization_name' => 'Other Org']);

        CertificateBatch::factory()->create([
            'user_id' => $acme->id,
            'original_filename' => 'acme-batch.csv',
            'status' => 'completed',
        ]);
        CertificateBatch::factory()->create([
            'user_id' => $other->id,
            'original_filename' => 'other-batch.csv',
            'status' => 'completed',
        ]);

        Livewire::actingAs($admin)
            ->test(BulkUploadHistory::class)
            ->assertSee('acme-batch.csv')
            ->assertSee('other-batch.csv')
            ->set('user_id', (string) $acme->id)
            ->assertSee('acme-batch.csv')
            ->assertDontSee('other-batch.csv');
    }

    public function test_status_filter_is_reactive(): void
    {
        $user = User::factory()->create();

        CertificateBatch::factory()->create([
            'user_id' => $user->id,
            'original_filename' => 'done.csv',
            'status' => 'completed',
        ]);
        CertificateBatch::factory()->create([
            'user_id' => $user->id,
            'original_filename' => 'failed.csv',
            'status' => 'failed',
        ]);

        Livewire::actingAs($user)
            ->test(BulkUploadHistory::class)
            ->set('status', 'failed')
            ->assertSee('failed.csv')
            ->assertDontSee('done.csv');
    }

    public function test_query_string_status_hydrates_on_mount(): void
    {
        $user = User::factory()->create();

        CertificateBatch::factory()->create([
            'user_id' => $user->id,
            'original_filename' => 'done.csv',
            'status' => 'completed',
        ]);
        CertificateBatch::factory()->create([
            'user_id' => $user->id,
            'original_filename' => 'failed.csv',
            'status' => 'failed',
        ]);

        $this->actingAs($user)
            ->get(route('bulk-upload.history', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('failed.csv')
            ->assertDontSee('done.csv');
    }
}
