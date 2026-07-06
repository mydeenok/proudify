<?php

namespace App\Jobs\Certificates;

use App\Jobs\Certificates\Concerns\RefreshesCertificateRecord;
use App\Mail\CertificateIssuedMail;
use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCertificateIssuedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RefreshesCertificateRecord, SerializesModels;

    public int $tries = 3;

    public function __construct(public Certificate $certificate)
    {
        $this->onQueue(config('certificates.queues.mail'));
    }

    public function handle(): void
    {
        $certificate = $this->freshCertificate();

        Mail::to($certificate->recipient_email)
            ->send(new CertificateIssuedMail($certificate));
    }
}
