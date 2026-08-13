<?php

namespace App\Jobs\Bulk;

use App\Models\CertificateBatch;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

/**
 * Chunks a batch's pending rows into a Bus::batch() of
 * ProcessCertificateBatchChunkJob, then finalizes once every chunk has
 * run. This replaces the reference app's synchronous, single-request
 * bulk-upload loop entirely.
 */
class DispatchCertificateBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // This job isn't idempotent - re-running handle() re-queries "pending"
    // items and dispatches a fresh set of chunk jobs, so an infra-level
    // retry (worker crash mid-job) after some chunks already went out
    // would duplicate-process those rows. Matches ProcessCertificateBatchChunkJob's
    // own tries=1 - failing without retry is safer than retrying and
    // double-processing.
    public int $tries = 1;

    public function __construct(public CertificateBatch $batch)
    {
        $this->onQueue(config('certificates.queues.bulk'));
    }

    public function handle(): void
    {
        $this->batch->forceFill(['status' => 'processing'])->save();

        $chunkSize = (int) config('certificates.bulk_upload_chunk_size');
        $itemIdChunks = $this->batch->items()
            ->where('status', 'pending')
            ->pluck('id')
            ->chunk($chunkSize);

        $jobs = $itemIdChunks
            ->map(fn ($chunk) => new ProcessCertificateBatchChunkJob($this->batch, $chunk->all()))
            ->all();

        $batchId = $this->batch->id;

        Bus::batch($jobs)
            ->onQueue(config('certificates.queues.bulk'))
            // Without this, Laravel's default batch behavior cancels every
            // not-yet-run chunk the moment any one chunk job fails - the
            // remaining rows are silently never attempted (left at
            // 'pending' forever) while FinalizeCertificateBatchJob still
            // runs off ->finally() and reports the batch done based on
            // whatever succeeded/failed counts happened to exist by then.
            ->allowFailures()
            ->finally(fn (Batch $batch) => FinalizeCertificateBatchJob::dispatch($batchId))
            ->dispatch();
    }
}
