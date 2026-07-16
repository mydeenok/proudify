<?php

namespace App\Notifications;

use App\Models\CertificateBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BulkUploadCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CertificateBatch $batch)
    {
        $this->onQueue(config('certificates.queues.mail'));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your bulk certificate upload is complete')
            ->view('emails.bulk-upload-completed', ['notifiable' => $notifiable, 'batch' => $this->batch]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = match (true) {
            $this->batch->failed_rows === 0 => 'Bulk upload completed',
            $this->batch->succeeded_rows === 0 => 'Bulk upload failed',
            default => 'Bulk upload completed with errors',
        };

        return [
            'title' => $title,
            'body' => "{$this->batch->succeeded_rows} of {$this->batch->total_rows} certificates issued".
                ($this->batch->failed_rows > 0 ? ", {$this->batch->failed_rows} failed" : '').'.',
            // Deliberately not a pre-built absolute URL: this notification is
            // created inside a queued job with no HTTP request, so route()
            // here would resolve against APP_URL instead of the real host.
            // Store just enough to build the route later, when it's actually
            // opened from a real request (see NotificationController::open()).
            'route' => 'bulk-upload.status',
            'route_params' => ['batch' => $this->batch->id],
        ];
    }
}
