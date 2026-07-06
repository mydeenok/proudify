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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your bulk certificate upload is complete')
            ->greeting("Hi {$notifiable->first_name},")
            ->line("Your batch of {$this->batch->total_rows} rows has finished processing.")
            ->line("{$this->batch->succeeded_rows} certificates were issued successfully.");

        if ($this->batch->failed_rows > 0) {
            $message->line("{$this->batch->failed_rows} rows could not be processed — see the error report for details.");
        }

        return $message->action('View Batch', route('bulk-upload.status', $this->batch));
    }
}
