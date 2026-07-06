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
            ->finally(fn (Batch $batch) => FinalizeCertificateBatchJob::dispatch($batchId))
            ->dispatch();
    }
}
