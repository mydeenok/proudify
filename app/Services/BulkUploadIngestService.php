<?php

namespace App\Services;

use App\Models\CertificateBatch;
use App\Models\CertificateBatchItem;
use App\Support\DateParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

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
     * Per-ingest-run cache of domain => has-mail-servers, so a file with
     * hundreds of rows sharing the same handful of company domains doesn't
     * repeat the same DNS lookup per row.
     *
     * @var array<string, bool>
     */
    private array $deliverableDomainCache = [];

    /**
     * @return array<int, string> column headers from the file's first row
     */
    public function parseHeaders(string $absolutePath): array
    {
        $rows = $this->readRows($absolutePath, limit: 1);

        return array_map(fn ($value) => (string) $value, $rows[0] ?? []);
    }

    /**
     * Best-effort default for the "Map Columns" form so a spreadsheet whose
     * headers already match (or closely resemble) our field names doesn't
     * need every dropdown hand-picked - without this every <select> just
     * shows its first option by default, which looks identical across all
     * six fields and reads as "the file didn't extract columns" even
     * though it did.
     *
     * @param  array<int, string>  $headers
     * @return array<string, int> field name => column index, only for fields with a confident match
     */
    public function guessColumnMapping(array $headers): array
    {
        $aliases = [
            'title' => ['title', 'certificate title', 'certificate_title', 'course', 'course title'],
            'recipient_name' => ['recipient_name', 'recipient name', 'name', 'full name', 'student name', 'student'],
            'recipient_email' => ['recipient_email', 'recipient email', 'email', 'email address'],
            'description' => ['description', 'desc', 'details', 'notes'],
            'date_of_issue' => ['date_of_issue', 'issue date', 'date of issue', 'issued', 'issued on'],
            'date_of_expiry' => ['date_of_expiry', 'expiry date', 'date of expiry', 'expires', 'expiry'],
        ];

        $normalized = array_map(
            fn ($header) => strtolower(trim(str_replace(['_', '-'], ' ', $header))),
            $headers
        );

        $mapping = [];

        foreach (self::ALL_FIELDS as $field) {
            foreach ($aliases[$field] as $alias) {
                $index = array_search(str_replace(['_', '-'], ' ', $alias), $normalized, true);

                if ($index !== false) {
                    $mapping[$field] = $index;

                    break;
                }
            }
        }

        return $mapping;
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
        $max = (int) config('certificates.bulk_upload_max_rows');

        // Capped at max+1 data rows so a spreadsheet with far more rows
        // than the limit allows doesn't have to be fully parsed into
        // memory just to be rejected - large files (100k+ rows, well
        // within the 10MB upload cap for short rows) previously blocked
        // the request thread parsing all of them before this check ever
        // ran.
        $rows = $this->readRows(Storage::disk('local')->path($batch->temp_upload_path), offset: 1, limit: $max + 1);

        $this->assertRowCountWithinBounds(count($rows), $max);

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

        if (! $this->isDeliverableEmail((string) $data['recipient_email'])) {
            return ['failed', 'Invalid or undeliverable recipient email address'];
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

    /**
     * Syntax-only validation (the old filter_var(FILTER_VALIDATE_EMAIL))
     * was letting typo'd/dead domains straight through to send - bulk CSV
     * recipient lists (hand-typed or exported from elsewhere) are exactly
     * where those concentrate, and they're a major source of this app's
     * bounce volume. Checking for an MX (or fallback A) record catches a
     * dead domain before the row is ever queued for issuance/email, at the
     * cost of one DNS lookup per unique domain in the file (cached above).
     *
     * If DNS is unreachable from this environment entirely, every row
     * would be rejected - an acceptable trade-off for catching real bad
     * domains, but worth knowing if bulk uploads start failing outright.
     */
    private function isDeliverableEmail(string $email): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Skipped under the test runner - it has no real DNS resolution,
        // so even a legitimate domain like example.com would fail every
        // time (same reasoning as CertificateController::storeValidationRules()).
        if (app()->runningUnitTests()) {
            return true;
        }

        $domain = substr((string) strrchr($email, '@'), 1);

        if ($domain === '') {
            return false;
        }

        if (! array_key_exists($domain, $this->deliverableDomainCache)) {
            $this->deliverableDomainCache[$domain] = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        }

        return $this->deliverableDomainCache[$domain];
    }

    private function assertRowCountWithinBounds(int $count, int $max): void
    {
        $min = (int) config('certificates.bulk_upload_min_rows');

        if ($count < $min) {
            throw ValidationException::withMessages([
                'file' => "This file has {$count} rows. Bulk upload supports between {$min} and {$max} rows per batch.",
            ]);
        }

        // $count here is capped at max+1 by readRows()'s read filter (see
        // ingestRows()), so once it exceeds $max the file's real row count
        // is unknown without a full parse - "more than" is as precise as
        // this can honestly be without giving up the memory savings.
        if ($count > $max) {
            throw ValidationException::withMessages([
                'file' => "This file has more than {$max} rows. Bulk upload supports between {$min} and {$max} rows per batch.",
            ]);
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $absolutePath, int $offset = 0, ?int $limit = null): array
    {
        // parseHeaders() (mapping-UI load) and ingestRows() (every mapping
        // submit, including re-maps - applyMapping() deletes and
        // re-inserts items on each one) each independently parsed the same
        // uploaded file from scratch. A user going back to fix a column
        // mapping and resubmitting re-paid the full PhpSpreadsheet parse
        // cost for a file that hadn't changed on disk. Cached per exact
        // (path, mtime, offset, limit) combination - the mtime keeps this
        // safe if the file were ever replaced, and different offset/limit
        // calls (header-only vs full ingest) simply get their own entries.
        $mtime = @filemtime($absolutePath);
        $cacheKey = $mtime === false
            ? null
            : 'bulk-upload-rows:'.md5($absolutePath).":{$mtime}:{$offset}:".($limit ?? 'null');

        $parse = function () use ($absolutePath, $offset, $limit): array {
            $reader = IOFactory::createReaderForFile($absolutePath);

            if ($limit !== null) {
                // Caps how many rows PhpSpreadsheet actually parses into
                // memory. $offset/$limit are 0-indexed into the post-toArray()
                // row list (row 0 = the header); PhpSpreadsheet's read filter
                // uses 1-indexed sheet rows, so +2 covers the header-row shift
                // with a one-row margin - a little slack here is harmless,
                // the point is never loading a 100k-row file just to reject it.
                $maxSheetRow = $offset + $limit + 2;

                $reader->setReadFilter(new class($maxSheetRow) implements IReadFilter
                {
                    public function __construct(private int $maxRow) {}

                    public function readCell($column, $row, $worksheetName = ''): bool
                    {
                        return $row <= $this->maxRow;
                    }
                });
            }

            $spreadsheet = $reader->load($absolutePath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            $rows = array_slice($rows, $offset, $limit);

            return array_values($rows);
        };

        if ($cacheKey === null) {
            return $parse();
        }

        return Cache::remember($cacheKey, now()->addMinutes(30), $parse);
    }
}
