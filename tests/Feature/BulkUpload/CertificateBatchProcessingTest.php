<?php

namespace Tests\Feature\BulkUpload;

use App\Jobs\Bulk\FinalizeCertificateBatchJob;
use App\Jobs\Bulk\ProcessCertificateBatchChunkJob;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateBatchItem;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CertificateBatchProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_a_chunk_issues_certificates_and_updates_counters(): void
    {
        Bus::fake([\App\Jobs\Certificates\GenerateCertificateQrCodeJob::class]);

        $user = User::factory()->create();
        $template = Template::factory()->create();
        UserSubscription::factory()->for($user)->create(['certificates_limit' => 50, 'users_limit' => 50]);
        $batch = CertificateBatch::factory()->for($user)->for($template)->create(['total_rows' => 2]);

        $items = collect([
            ['title' => 'Cert A', 'recipient_name' => 'Alice', 'recipient_email' => 'alice@example.com', 'date_of_issue' => '2026-01-01'],
            ['title' => 'Cert B', 'recipient_name' => 'Bob', 'recipient_email' => 'bob@example.com', 'date_of_issue' => '2026-01-02'],
        ])->map(fn ($data, $i) => CertificateBatchItem::create([
            'certificate_batch_id' => $batch->id,
            'row_number' => $i + 2,
            'row_data' => $data,
            'status' => 'pending',
        ]));

        $job = new ProcessCertificateBatchChunkJob($batch, $items->pluck('id')->all());
        $job->handle(app(\App\Actions\Certificates\IssueSingleCertificateAction::class));

        $batch->refresh();
        $this->assertSame(2, $batch->processed_rows);
        $this->assertSame(2, $batch->succeeded_rows);
        $this->assertSame(0, $batch->failed_rows);
        $this->assertSame(2, Certificate::where('certificate_batch_id', $batch->id)->count());
        $this->assertSame(2, $batch->items()->where('status', 'succeeded')->count());
    }

    public function test_a_row_that_fails_issuance_is_marked_failed_without_aborting_the_chunk(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create();
        UserSubscription::factory()->for($user)->create(['certificates_limit' => 50, 'users_limit' => 50]);
        $batch = CertificateBatch::factory()->for($user)->for($template)->create();

        // Missing date_of_issue will throw when Certificate::create() hits
        // the not-null column, simulating a bad row surviving ingest.
        $badItem = CertificateBatchItem::create([
            'certificate_batch_id' => $batch->id,
            'row_number' => 2,
            'row_data' => ['title' => 'Cert A', 'recipient_name' => 'Alice', 'recipient_email' => 'alice@example.com'],
            'status' => 'pending',
        ]);

        $job = new ProcessCertificateBatchChunkJob($batch, [$badItem->id]);
        $job->handle(app(\App\Actions\Certificates\IssueSingleCertificateAction::class));

        $batch->refresh();
        $this->assertSame(1, $batch->failed_rows);
        $this->assertSame(0, $batch->succeeded_rows);
        $this->assertSame('failed', $badItem->fresh()->status);
        $this->assertNotNull($badItem->fresh()->error_message);
    }

    public function test_finalize_marks_batch_completed_when_nothing_failed(): void
    {
        Notification::fake();

        $batch = CertificateBatch::factory()->create([
            'total_rows' => 2, 'processed_rows' => 2, 'succeeded_rows' => 2, 'failed_rows' => 0,
        ]);

        (new FinalizeCertificateBatchJob($batch->id))->handle();

        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertNull($batch->fresh()->error_report_path);
    }

    public function test_finalize_marks_completed_with_errors_and_writes_a_report_when_some_rows_failed(): void
    {
        Notification::fake();

        $batch = CertificateBatch::factory()->create([
            'total_rows' => 2, 'processed_rows' => 2, 'succeeded_rows' => 1, 'failed_rows' => 1,
        ]);
        CertificateBatchItem::create([
            'certificate_batch_id' => $batch->id, 'row_number' => 2,
            'row_data' => ['recipient_email' => 'bad@example.com'], 'status' => 'failed', 'error_message' => 'Invalid date',
        ]);

        (new FinalizeCertificateBatchJob($batch->id))->handle();

        $batch->refresh();
        $this->assertSame('completed_with_errors', $batch->status);
        $this->assertNotNull($batch->error_report_path);
    }

    public function test_finalize_marks_failed_when_nothing_succeeded(): void
    {
        Notification::fake();

        $batch = CertificateBatch::factory()->create([
            'total_rows' => 1, 'processed_rows' => 1, 'succeeded_rows' => 0, 'failed_rows' => 1,
        ]);

        (new FinalizeCertificateBatchJob($batch->id))->handle();

        $this->assertSame('failed', $batch->fresh()->status);
    }
}
