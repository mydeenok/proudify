<?php

namespace App\Jobs\Bulk;

use App\Models\CertificateBatch;
use App\Notifications\BulkUploadCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FinalizeCertificateBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $batchId)
    {
        $this->onQueue(config('certificates.queues.bulk'));
    }

    public function handle(): void
    {
        $batch = CertificateBatch::with('user')->findOrFail($this->batchId);

        $batch->forceFill([
            'status' => match (true) {
                $batch->failed_rows === 0 => 'completed',
                $batch->succeeded_rows === 0 => 'failed',
                default => 'completed_with_errors',
            },
        ])->save();

        if ($batch->failed_rows > 0) {
            $batch->forceFill(['error_report_path' => $this->writeErrorReport($batch)])->save();
        }

        $batch->user->notify(new BulkUploadCompletedNotification($batch));
    }

    private function writeErrorReport(CertificateBatch $batch): string
    {
        $problemRows = $batch->items()->whereIn('status', ['failed', 'skipped'])->get();

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Row', 'Status', 'Recipient', 'Error']);

        foreach ($problemRows as $item) {
            $recipient = $item->row_data['recipient_email'] ?? '';
            fputcsv($handle, [
                $item->row_number,
                $item->status,
                $this->neutralizeFormula($recipient),
                $this->neutralizeFormula((string) $item->error_message),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $path = "certificate-batches/{$batch->id}/".Str::random(40)."/error-report.csv";
        Storage::disk('local')->put($path, $csv);

        return $path;
    }

    /**
     * Recipient values come straight from a spreadsheet a tenant uploaded,
     * so a cell starting with =, +, -, or @ would otherwise be interpreted
     * as a live formula by Excel/Sheets/LibreOffice when this report is
     * opened later (CSV/formula injection, CWE-1236) — prefixing it with a
     * single quote forces spreadsheet apps to treat it as inert text.
     */
    private function neutralizeFormula(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
