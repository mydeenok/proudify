<?php

namespace App\Jobs\Bulk;

use App\Actions\Certificates\IssueSingleCertificateAction;
use App\Exceptions\SubscriptionQuotaExceededException;
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

        foreach ($items as $index => $item) {
            $item->forceFill(['status' => 'processing'])->save();

            try {
                // The lock that matters here is on the subscription usage
                // counter inside IssueSingleCertificateAction/
                // SubscriptionQuotaService — it's what stops concurrent
                // chunks from overshooting the plan limit, not anything at
                // this job's level.
                $certificate = $action->execute($this->batch->user, $this->batch->template, $item->row_data, $this->batch);

                $item->forceFill(['status' => 'succeeded', 'certificate_id' => $certificate->id])->save();

                $this->incrementBatchCounters(succeeded: 1);
            } catch (SubscriptionQuotaExceededException $exception) {
                $item->forceFill(['status' => 'skipped', 'error_message' => $exception->getMessage()])->save();
                $this->incrementBatchCounters(failed: 1);

                // Quota is exhausted for the whole batch, not just this
                // row — every remaining item in this chunk would fail
                // identically, so stop cleanly instead of hammering the
                // lock for a doomed result on each one.
                $this->skipRemaining($items->slice($index + 1), $exception->getMessage());

                return;
            } catch (Throwable $exception) {
                $item->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage()])->save();

                $this->incrementBatchCounters(failed: 1);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CertificateBatchItem>  $remaining
     */
    private function skipRemaining($remaining, string $reason): void
    {
        if ($remaining->isEmpty()) {
            return;
        }

        CertificateBatchItem::whereIn('id', $remaining->pluck('id'))
            ->update(['status' => 'skipped', 'error_message' => $reason]);

        $this->incrementBatchCounters(failed: $remaining->count());
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
