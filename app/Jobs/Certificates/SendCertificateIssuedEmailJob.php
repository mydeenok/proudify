<?php

namespace App\Jobs\Certificates;

use App\Jobs\Certificates\Concerns\RefreshesCertificateRecord;
use App\Mail\CertificateIssuedMail;
use App\Models\Certificate;
use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCertificateIssuedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RefreshesCertificateRecord, SerializesModels;

    // A rejected/nonexistent recipient address won't become valid on retry
    // #2 or #3 - retrying just re-sends (and re-bounces) against the same
    // bad address, tripling the apparent bounce count for what's really
    // one bad email. One attempt, fail fast, surface it via failed() below.
    public int $tries = 1;

    public function __construct(public Certificate $certificate)
    {
        $this->onQueue(config('certificates.queues.mail'));
    }

    /**
     * Without this, retry_after being shorter than a slow SMTP send could
     * let a second worker pick up and re-run this same job while the first
     * attempt was still in flight, sending the certificate email twice.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->certificate->id)];
    }

    public function handle(): void
    {
        $certificate = $this->freshCertificate();

        try {
            Mail::to($certificate->recipient_email)
                ->send(new CertificateIssuedMail($certificate));
        } catch (Throwable $exception) {
            $this->logDelivery('failed', $exception->getMessage());

            throw $exception;
        }

        $this->logDelivery('sent');
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Certificate-issued email failed permanently.', [
            'certificate_id' => $this->certificate->id,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * CertificateIssuedMail is sent via the raw Mail facade rather than
     * Laravel's Notification system, so App\Listeners\LogEmailDelivery
     * (which only listens for NotificationSent/NotificationFailed) never
     * saw it - the app's highest-volume, highest-bounce-risk email was
     * entirely invisible in EmailLog. Logged directly here instead.
     */
    private function logDelivery(string $status, ?string $errorMessage = null): void
    {
        EmailLog::create([
            'notification_class' => CertificateIssuedMail::class,
            'recipient_email' => $this->certificate->recipient_email,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
