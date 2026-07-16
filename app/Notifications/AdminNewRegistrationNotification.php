<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNewRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $registrant)
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
            ->subject('New registration awaiting your approval')
            ->view('emails.admin-new-registration', ['registrant' => $this->registrant]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New registration awaiting approval',
            'body' => "{$this->registrant->name} ({$this->registrant->organization_name}) verified their email and is waiting for approval.",
            'route' => 'admin.users.unapproved',
            'route_params' => [],
        ];
    }
}
