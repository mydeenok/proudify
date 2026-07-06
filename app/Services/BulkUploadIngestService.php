<?php

namespace App\Services;

use App\Models\CertificateBatch;
use App\Models\CertificateBatchItem;
use App\Support\DateParser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parses an uploaded spreadsheet into CertificateBatchItem rows, applying
 * the column mapping from the wizard's "Map Columns" step, enforcing the
 * row-count cap, and flagging duplicates/invalid rows before anything is
 * queued for issuance.
 */
class BulkUploadIngestService
{
    private const REQUIRED_FIELDS = ['title', 'recipient_name', 'recipient_email', 'date_of_issue'];

    private const ALL_FIELDS = ['title', 'recipient_name', 'recipient_email', 'description', 'date_of_issue', 'date_of_expiry'];

    /**
     * @return array<int, string> column headers from the file's first row
     */
    public function parseHeaders(string $absolutePath): array
    {
        $rows = $this->readRows($absolutePath, limit: 1);

        return array_map(fn ($value) => (string) $value, $rows[0] ?? []);
    }

    /**
     * Reads every data row, applies the column mapping, validates and
     * de-duplicates, then persists CertificateBatchItem rows. Throws
     * ValidationException (caught by the controller) if the row count is
     * outside the configured bounds — nothing is persisted in that case.
     *
     * @param  array<string, int>  $columnMapping  field name => column index
     */
    public function ingestRows(CertificateBatch $batch, array $columnMapping): void
    {
        $rows = $this->readRows(Storage::disk('local')->path($batch->temp_upload_path), offset: 1);

        $this->assertRowCountWithinBounds(count($rows));

        $seen = [];
        $items = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for zero-index, +1 for the header row
            $data = $this->mapRow($row, $columnMapping);

            [$status, $errorMessage] = $this->validateRow($data, $seen);

            $items[] = [
                'certificate_batch_id' => $batch->id,
                'row_number' => $rowNumber,
                'row_data' => json_encode($data),
                'status' => $status,
                'error_message' => $errorMessage,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        CertificateBatchItem::insert($items);

        $batch->forceFill([
            'total_rows' => count($items),
            'status' => 'queued',
        ])->save();
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columnMapping
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $columnMapping): array
    {
        $data = [];

        foreach (self::ALL_FIELDS as $field) {
            $columnIndex = $columnMapping[$field] ?? null;
            $value = $columnIndex !== null ? ($row[$columnIndex] ?? null) : null;

            $data[$field] = in_array($field, ['date_of_issue', 'date_of_expiry'], true)
                ? DateParser::parse($value)?->toDateString()
                : (is_string($value) ? trim($value) : $value);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $seen  identifying-tuple => first row number seen, mutated by reference
     * @return array{0: string, 1: ?string} [status, error_message]
     */
    private function validateRow(array $data, array &$seen): array
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($data[$field])) {
                return ['failed', "Missing required field: {$field}"];
            }
        }

        if (! filter_var($data['recipient_email'], FILTER_VALIDATE_EMAIL)) {
            return ['failed', 'Invalid recipient email address'];
        }

        // Duplicate detection across every identifying field, not just
        // email — matches the one thing the reference app's bulk upload
        // got right, since two different recipients can share an email
        // (e.g. a shared department inbox) but not an entire row.
        $identity = implode('|', [
            $data['title'], $data['recipient_email'], $data['description'],
            $data['date_of_issue'], $data['date_of_expiry'],
        ]);

        if (isset($seen[$identity])) {
            return ['skipped', "Duplicate of row {$seen[$identity]}"];
        }

        $seen[$identity] = true;

        return ['pending', null];
    }

    private function assertRowCountWithinBounds(int $count): void
    {
        $min = (int) config('certificates.bulk_upload_min_rows');
        $max = (int) config('certificates.bulk_upload_max_rows');

        if ($count < $min || $count > $max) {
            throw ValidationException::withMessages([
                'file' => "This file has {$count} rows. Bulk upload supports between {$min} and {$max} rows per batch.",
            ]);
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $absolutePath, int $offset = 0, ?int $limit = null): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $rows = array_slice($rows, $offset, $limit);

        return array_values($rows);
    }
}
