<?php

namespace App\Notifications;

use App\Models\CertificateBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminBulkUploadRequestedNotification extends Notification implements ShouldQueue
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
            ->subject('New bulk certificate request submitted')
            ->view('emails.admin-bulk-upload-requested', ['batch' => $this->batch]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New bulk certificate request',
            'body' => "{$this->batch->user->organization_name} requested {$this->batch->total_rows} certificates using {$this->batch->template->name}.",
            // Same click-time route resolution as BulkUploadCompletedNotification -
            // status.blade.php is already role-aware, so this takes an admin
            // straight to the batch's live progress.
            'route' => 'bulk-upload.status',
            'route_params' => ['batch' => $this->batch->id],
        ];
    }
}
