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
            $item->forceFill(['status' => 'processing'])->save();

            try {
                $certificate = $action->execute($this->batch->user, $this->batch->template, $item->row_data, $this->batch);

                $item->forceFill(['status' => 'succeeded', 'certificate_id' => $certificate->id])->save();

                $this->incrementBatchCounters(succeeded: 1);
            } catch (Throwable $exception) {
                $item->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage()])->save();

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
