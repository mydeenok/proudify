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

        $csv = "Row,Status,Recipient,Error\n";

        foreach ($problemRows as $item) {
            $recipient = $item->row_data['recipient_email'] ?? '';
            $csv .= "{$item->row_number},{$item->status},\"{$recipient}\",\"{$item->error_message}\"\n";
        }

        $path = "certificate-batches/{$batch->id}/error-report.csv";
        Storage::disk('public')->put($path, $csv);

        return $path;
    }
}
