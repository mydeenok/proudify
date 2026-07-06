<?php

namespace App\Jobs\Certificates;

use App\Models\Certificate;
use App\Services\QrCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCertificateQrCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
}
