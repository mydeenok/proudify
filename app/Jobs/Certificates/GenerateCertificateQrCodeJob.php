<?php

namespace App\Jobs\Certificates;

use App\Jobs\Certificates\Concerns\RefreshesCertificateRecord;
use App\Models\Certificate;
use App\Services\QrCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateCertificateQrCodeJob implements ShouldQueue
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

    public function handle(QrCodeService $qrCodeService): void
    {
        $path = $qrCodeService->generate($this->certificate);

        $this->certificate->forceFill(['qr_code_path' => $path])->save();
    }

    /**
     * QR is first in the chain (see IssueSingleCertificateAction), so a
     * permanent failure here means the PDF/image jobs never even run.
     * Without marking a terminal status, the certificate is stuck at
     * whatever image_generation_status defaults to forever, invisible to
     * both the user and CertificateController::queueMissingGenerationJobs().
     */
    public function failed(Throwable $exception): void
    {
        $this->freshCertificate()->forceFill(['image_generation_status' => 'failed'])->save();

        Log::error('Certificate QR code generation failed permanently.', [
            'certificate_id' => $this->certificate->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
