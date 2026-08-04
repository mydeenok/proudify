<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminContactRequestNotification extends Notification
{
    public function __construct(public ContactRequest $contactRequest) {}

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
            ->subject('New contact request: '.$this->contactRequest->subject)
            ->view('emails.admin-contact-request', ['contactRequest' => $this->contactRequest]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New contact request',
            'body' => "{$this->contactRequest->name} wrote: {$this->contactRequest->subject}",
            'route' => 'admin.contact-requests.show',
            'route_params' => ['contactRequest' => $this->contactRequest->id],
        ];
    }
}
