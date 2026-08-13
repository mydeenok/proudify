<?php

namespace App\Services;

use App\Jobs\Bulk\DispatchCertificateBatchJob;
use App\Models\CertificateBatch;
use App\Models\Template;
use App\Models\User;
use App\Notifications\AdminBulkUploadRequestedNotification;
use App\Support\NotifyAdmins;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Shared create / map / confirm steps for the bulk-upload wizard.
 * Used by Livewire and by the thin HTTP controller wrappers.
 */
class BulkUploadWizardService
{
    public function __construct(
        private BulkUploadIngestService $ingestService,
    ) {}

    public function createTenantBatch(User $owner, int $templateId, UploadedFile $file): CertificateBatch
    {
        $template = Template::active()->findOrFail($templateId);

        return CertificateBatch::create([
            'user_id' => $owner->id,
            'template_id' => $template->id,
            'original_filename' => $file->getClientOriginalName(),
            'temp_upload_path' => $file->store('bulk-upload-temp', 'local'),
            'status' => 'mapping',
        ]);
    }

    public function createAdminBatch(User $admin, int $userId, int $templateId, UploadedFile $file): CertificateBatch
    {
        $template = Template::active()->findOrFail($templateId);

        return CertificateBatch::create([
            'user_id' => $userId,
            'template_id' => $template->id,
            'issued_by' => $admin->id,
            'original_filename' => $file->getClientOriginalName(),
            'temp_upload_path' => $file->store('bulk-upload-temp', 'local'),
            'status' => 'mapping',
        ]);
    }

    /**
     * @param  array<string, int|string|null>  $mapping
     *
     * @throws ValidationException
     */
    public function applyMapping(CertificateBatch $batch, array $mapping): void
    {
        $normalized = $this->normalizeMapping($mapping);

        $batch->forceFill(['column_mapping' => $normalized])->save();

        // Re-map after a prior ingest would otherwise duplicate items.
        $batch->items()->delete();
        $batch->forceFill([
            'total_rows' => 0,
            'processed_rows' => 0,
            'succeeded_rows' => 0,
            'failed_rows' => 0,
            'status' => 'mapping',
        ])->save();

        $this->ingestService->ingestRows($batch, $normalized);
    }

    public function confirm(CertificateBatch $batch): void
    {
        $pendingCount = $batch->items()->where('status', 'pending')->count();

        if ($pendingCount === 0) {
            throw ValidationException::withMessages([
                'batch' => 'No rows are ready to issue. Check your file and column mapping.',
            ]);
        }

        // Admin-issued batches (issued_by set) stay free and never get a
        // CertificateOrder - anything else MUST have one, paid, with a
        // matching row count. This is the single funnel every path into
        // dispatch goes through (the Livewire wizard's confirm(),
        // CertificateOrderCompletionService after payment, and a forged
        // direct POST to the legacy confirm route), so neither a payment
        // skip nor a stale second tab re-mapping rows after payment can
        // slip a batch through.
        if ($batch->issued_by === null) {
            $order = $batch->certificateOrder;

            if (! $order || $order->status !== 'paid' || $order->quantity !== $pendingCount) {
                throw ValidationException::withMessages([
                    'batch' => 'This batch has not been paid for, or its row count changed after payment. Please review and pay again.',
                ]);
            }
        }

        // Idempotency: atomically claim the batch for dispatch so a
        // double-click, slow-network resubmit, or the payment-completion
        // path racing a manual retry can't queue the same rows twice. Only
        // the call that actually flips queued -> processing gets to
        // dispatch; every other concurrent caller sees 0 rows affected and
        // returns as a safe no-op.
        $claimed = CertificateBatch::whereKey($batch->id)
            ->where('status', 'queued')
            ->update(['status' => 'processing']);

        if ($claimed === 0) {
            return;
        }

        DispatchCertificateBatchJob::dispatch($batch);

        $batch->loadMissing('user', 'template');
        NotifyAdmins::notify(
            new AdminBulkUploadRequestedNotification($batch),
            'Failed to send admin bulk-upload-requested alert.',
            ['batch_id' => $batch->id],
        );
    }

    /**
     * @param  array<string, int|string|null>  $mapping
     * @return array<string, int>
     */
    private function normalizeMapping(array $mapping): array
    {
        $normalized = [];

        foreach (['title', 'recipient_name', 'recipient_email', 'description', 'date_of_issue', 'date_of_expiry'] as $field) {
            if (! array_key_exists($field, $mapping) || $mapping[$field] === '' || $mapping[$field] === null) {
                continue;
            }

            $normalized[$field] = (int) $mapping[$field];
        }

        return $normalized;
    }
}
