<?php

namespace App\Jobs\Certificates;

use App\Jobs\Certificates\Concerns\RefreshesCertificateRecord;
use App\Models\Certificate;
use App\Services\CertificateRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateCertificatePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RefreshesCertificateRecord, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(public Certificate $certificate)
    {
        $this->onQueue(config('certificates.queues.certificates'));
        $this->tries = config('certificates.job_retries');
        $this->timeout = config('certificates.job_timeout');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->certificate->id)];
    }

    public function handle(CertificateRenderService $renderService): void
    {
        $certificate = $this->freshCertificate();

        $path = $renderService->renderPdf($certificate);

        $certificate->forceFill(['pdf_path' => $path])->save();
    }

    /**
     * Without this, a permanent failure here (e.g. the Node/Skia renderer
     * crashing on a broken template asset) left image_generation_status
     * exactly where it was before this job ran - 'pending' for a
     * fresh bulk-issued certificate, never flipping to a terminal state.
     * CertificateController::queueMissingGenerationJobs() reads that same
     * field to decide whether to re-dispatch, so a certificate stuck like
     * this got a brand new (and just as doomed) GenerateCertificatePdfJob
     * queued on every single /status poll from the user's open tab.
     */
    public function failed(Throwable $exception): void
    {
        $this->freshCertificate()->forceFill(['image_generation_status' => 'failed'])->save();

        Log::error('Certificate PDF generation failed permanently.', [
            'certificate_id' => $this->certificate->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
