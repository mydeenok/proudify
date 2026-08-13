<?php

namespace Tests\Feature\BulkUpload;

use App\Models\CertificateBatchItem;
use App\Models\Template;
use App\Models\User;
use App\Services\BulkUploadWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkUploadIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    // Deliberately two columns whose contents would be trivially swappable
    // by a stale cache - "Name" and "Email" columns 1 and 2.
    private const CSV = <<<'CSV'
        Title,Name,Email,Description,Issue Date,Expiry Date
        Certificate of Achievement,Alice Wong,alice@example.com,Great job,2026-01-01,
        CSV;

    /**
     * readRows() caches the raw parsed spreadsheet per (path, mtime,
     * offset, limit) so a re-map submission doesn't re-parse the file from
     * disk. This proves that cache holds raw rows, not mapped output - a
     * second applyMapping() call with columns 1/2 swapped must still
     * extract the right values from the (cached) raw grid, not silently
     * replay whatever the first mapping produced.
     */
    public function test_remapping_after_a_cached_parse_still_reflects_the_new_mapping(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create();
        $file = UploadedFile::fake()->createWithContent('recipients.csv', self::CSV);

        $wizard = app(BulkUploadWizardService::class);
        $batch = $wizard->createTenantBatch($user, $template->id, $file);

        $wizard->applyMapping($batch, [
            'title' => 0,
            'recipient_name' => 1,
            'recipient_email' => 2,
            'description' => 3,
            'date_of_issue' => 4,
            'date_of_expiry' => 5,
        ]);

        $firstRow = CertificateBatchItem::where('certificate_batch_id', $batch->id)->firstOrFail();
        $this->assertSame('Alice Wong', $firstRow->row_data['recipient_name']);
        $this->assertSame('alice@example.com', $firstRow->row_data['recipient_email']);

        // Re-map with columns 1 and 2 swapped - simulates a user going
        // back to "Map Columns" and fixing a mistake, hitting the exact
        // same (path, mtime) readRows() cache key as the first call.
        $wizard->applyMapping($batch, [
            'title' => 0,
            'recipient_name' => 2,
            'recipient_email' => 1,
            'description' => 3,
            'date_of_issue' => 4,
            'date_of_expiry' => 5,
        ]);

        $secondRow = CertificateBatchItem::where('certificate_batch_id', $batch->id)->firstOrFail();
        $this->assertSame(
            'alice@example.com',
            $secondRow->row_data['recipient_name'],
            'Cached raw rows must be re-mapped fresh (column 2), not replay the first mapping\'s value.'
        );
        $this->assertSame(
            'Alice Wong',
            $secondRow->row_data['recipient_email'],
            'Cached raw rows must be re-mapped fresh (column 1), not replay the first mapping\'s value.'
        );
    }
}
