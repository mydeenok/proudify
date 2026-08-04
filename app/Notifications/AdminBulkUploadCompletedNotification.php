<?php

namespace App\Notifications;

use App\Models\CertificateBatch;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminBulkUploadCompletedNotification extends Notification
{
    public function __construct(public CertificateBatch $batch) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->view('emails.admin-bulk-upload-completed', ['batch' => $this->batch]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $org = $this->batch->user?->organization_name ?? 'Unknown org';

        return [
            'title' => $this->title(),
            'body' => "{$org}: {$this->batch->succeeded_rows} of {$this->batch->total_rows} certificates issued".
                ($this->batch->failed_rows > 0 ? ", {$this->batch->failed_rows} failed" : '').'.',
            'route' => 'bulk-upload.status',
            'route_params' => ['batch' => $this->batch->id],
        ];
    }

    private function title(): string
    {
        return match (true) {
            $this->batch->failed_rows === 0 => 'Bulk upload completed',
            $this->batch->succeeded_rows === 0 => 'Bulk upload failed',
            default => 'Bulk upload completed with errors',
        };
    }
}
