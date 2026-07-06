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
}
