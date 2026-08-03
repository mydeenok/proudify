<?php

namespace Tests\Feature\BulkUpload;

use App\Jobs\Bulk\DispatchCertificateBatchJob;
use App\Livewire\BulkUploadWizard;
use App\Models\CertificateBatch;
use App\Models\Template;
use App\Models\User;
use App\Notifications\AdminBulkUploadRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class BulkUploadWizardTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = <<<'CSV'
        Title,Name,Email,Description,Issue Date,Expiry Date
        Certificate of Achievement,Alice Wong,alice@example.com,Great job,2026-01-01,
        Certificate of Achievement,Bob Lee,bob@example.com,Nice work,2026-01-02,
        Certificate of Achievement,Alice Wong,alice@example.com,Great job,2026-01-01,
        CSV;

    private const MAPPING = [
        'title' => 0,
        'recipient_name' => 1,
        'recipient_email' => 2,
        'description' => 3,
        'date_of_issue' => 4,
        'date_of_expiry' => 5,
    ];

    public function test_the_full_wizard_creates_a_batch_ready_to_confirm(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $template = Template::factory()->create();
        $file = UploadedFile::fake()->createWithContent('recipients.csv', self::CSV);

        $storeResponse = $this->actingAs($user)->post(route('bulk-upload.store'), [
            'template_id' => $template->id,
            'file' => $file,
        ]);

        $batch = CertificateBatch::firstOrFail();
        $storeResponse->assertRedirect(route('bulk-upload.map-columns', $batch));
        $this->assertSame('mapping', $batch->status);

        $mappingResponse = $this->actingAs($user)->post(route('bulk-upload.map-columns.store', $batch), [
            'mapping' => self::MAPPING,
        ]);

        $batch->refresh();
        $mappingResponse->assertRedirect(route('bulk-upload.review', $batch));
        $this->assertSame('queued', $batch->status);
        $this->assertSame(3, $batch->total_rows);
        $this->assertSame(2, $batch->items()->where('status', 'pending')->count());
        $this->assertSame(1, $batch->items()->where('status', 'skipped')->count());

        Bus::fake();

        $this->actingAs($user)->post(route('bulk-upload.confirm', $batch))
            ->assertRedirect(route('bulk-upload.status', $batch));

        Bus::assertDispatched(DispatchCertificateBatchJob::class);
        Notification::assertSentTo($admin, AdminBulkUploadRequestedNotification::class);
    }

    public function test_a_file_outside_the_row_cap_is_rejected_at_mapping_time(): void
    {
        config(['certificates.bulk_upload_max_rows' => 2]);

        $user = User::factory()->create();
        $template = Template::factory()->create();
        $file = UploadedFile::fake()->createWithContent('recipients.csv', self::CSV);

        $this->actingAs($user)->post(route('bulk-upload.store'), [
            'template_id' => $template->id,
            'file' => $file,
        ]);

        $batch = CertificateBatch::firstOrFail();

        $this->actingAs($user)->post(route('bulk-upload.map-columns.store', $batch), [
            'mapping' => self::MAPPING,
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, $batch->fresh()->items()->count());
    }

    public function test_a_user_cannot_access_another_users_batch(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $batch = CertificateBatch::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get(route('bulk-upload.status', $batch))
            ->assertForbidden();
    }

    public function test_livewire_wizard_runs_end_to_end_without_page_reloads(): void
    {
        Notification::fake();
        Bus::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $template = Template::factory()->create();
        $file = UploadedFile::fake()->createWithContent('recipients.csv', self::CSV);

        Livewire::actingAs($user)
            ->test(BulkUploadWizard::class, [
                'mode' => 'tenant',
                'step' => 'template',
            ])
            ->call('selectTemplate', $template->id)
            ->assertSet('step', 'upload')
            ->set('file', $file)
            ->call('upload')
            ->assertSet('step', 'map')
            ->set('mapping', self::MAPPING)
            ->call('saveMapping')
            ->assertSet('step', 'review')
            ->call('confirm')
            ->assertRedirect(route('bulk-upload.status', CertificateBatch::firstOrFail()));

        $batch = CertificateBatch::firstOrFail();
        $this->assertSame('queued', $batch->status);
        $this->assertSame(2, $batch->items()->where('status', 'pending')->count());
        $this->assertSame(1, $batch->items()->where('status', 'skipped')->count());
        Bus::assertDispatched(DispatchCertificateBatchJob::class);
        Notification::assertSentTo($admin, AdminBulkUploadRequestedNotification::class);
    }

    public function test_admin_livewire_wizard_sets_issued_by_and_target_user(): void
    {
        $admin = User::factory()->admin()->create();
        $orgUser = User::factory()->create(['organization_name' => 'Acme Org']);
        $template = Template::factory()->create();
        $file = UploadedFile::fake()->createWithContent('recipients.csv', self::CSV);

        Livewire::actingAs($admin)
            ->test(BulkUploadWizard::class, [
                'mode' => 'admin',
                'step' => 'setup',
            ])
            ->set('userId', $orgUser->id)
            ->set('templateId', $template->id)
            ->set('file', $file)
            ->call('adminUpload')
            ->assertSet('step', 'map');

        $batch = CertificateBatch::firstOrFail();
        $this->assertSame($orgUser->id, $batch->user_id);
        $this->assertSame($admin->id, $batch->issued_by);
        $this->assertSame('mapping', $batch->status);
    }

    public function test_guests_cannot_mount_the_livewire_wizard(): void
    {
        Livewire::test(BulkUploadWizard::class)
            ->assertForbidden();
    }

    public function test_select_template_page_hosts_the_livewire_wizard(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create(['name' => 'Graduation Classic']);

        $this->actingAs($user)
            ->get(route('bulk-upload.select-template'))
            ->assertOk()
            ->assertSeeLivewire(BulkUploadWizard::class)
            ->assertSee('Graduation Classic');
    }
}
