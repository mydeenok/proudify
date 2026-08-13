<?php

namespace App\Jobs\Bulk;

use App\Actions\Certificates\IssueSingleCertificateAction;
use App\Models\CertificateBatch;
use App\Models\CertificateBatchItem;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessCertificateBatchChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  array<int, int>  $itemIds
     */
    public function __construct(public CertificateBatch $batch, public array $itemIds)
    {
        $this->onQueue(config('certificates.queues.bulk'));
    }

    public function handle(IssueSingleCertificateAction $action): void
    {
        $items = CertificateBatchItem::whereIn('id', $this->itemIds)->get();

        foreach ($items as $item) {
            // The whole per-item body - including the initial "processing"
            // save - is inside this try/catch now. It used to sit outside:
            // a transient DB error on just that save (deadlock, lost
            // connection) threw out of handle() entirely, permanently
            // failing this chunk job (tries=1, no retry) and silently
            // skipping every remaining item in the chunk with no record of
            // why. One bad row can no longer take the rest of the chunk
            // down with it.
            try {
                $item->forceFill(['status' => 'processing'])->save();

                $certificate = $action->execute($this->batch->user, $this->batch->template, $item->row_data, $this->batch);

                $item->forceFill(['status' => 'succeeded', 'certificate_id' => $certificate->id])->save();

                $this->incrementBatchCounters(succeeded: 1);
            } catch (Throwable $exception) {
                try {
                    $item->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage()])->save();
                } catch (Throwable) {
                    // Best-effort - if even this save fails, fall through
                    // so the loop still reaches the next item.
                }

                $this->incrementBatchCounters(failed: 1);
            }
        }
    }

    private function incrementBatchCounters(int $succeeded = 0, int $failed = 0): void
    {
        DB::table('certificate_batches')->where('id', $this->batch->id)->update([
            'processed_rows' => DB::raw('processed_rows + '.($succeeded + $failed)),
            'succeeded_rows' => DB::raw('succeeded_rows + '.$succeeded),
            'failed_rows' => DB::raw('failed_rows + '.$failed),
        ]);
    }
}
