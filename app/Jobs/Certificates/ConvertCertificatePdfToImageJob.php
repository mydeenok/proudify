<?php

namespace App\Jobs\Certificates;

use App\Jobs\Certificates\Concerns\RefreshesCertificateRecord;
use App\Models\Certificate;
use App\Services\CertificateRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued PDF -> JPG preview conversion for the bulk-upload path, which
 * needs a real queue (hundreds of rows can't run synchronously in one
 * request). Single-certificate issuance instead calls
 * CertificateRenderService::convertPdfToImage() directly and inline — see
 * IssueSingleCertificateAction — so this job is a thin wrapper around that
 * same service, not a second copy of the conversion logic.
 */
class ConvertCertificatePdfToImageJob implements ShouldQueue
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
        return [
            new WithoutOverlapping($this->certificate->id),
            (new RateLimited('pdf-conversions'))->dontRelease(),
        ];
    }

    public function handle(CertificateRenderService $renderService): void
    {
        $this->certificate->forceFill(['image_generation_status' => 'processing'])->save();

        $imageRelativePath = $renderService->convertPdfToImage($this->certificate);

        $this->certificate->forceFill([
            'image_path' => $imageRelativePath,
            'image_generation_status' => 'completed',
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->freshCertificate()->forceFill(['image_generation_status' => 'failed'])->save();

        Log::error('Certificate PDF-to-image conversion failed permanently.', [
            'certificate_id' => $this->certificate->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
