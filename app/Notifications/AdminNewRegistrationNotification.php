<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately not ShouldQueue — this is a time-sensitive alert, and the
 * caller already loops over every admin inside its own try/catch so one
 * mail failure can't take down the registration flow or skip the rest of
 * the admins.
 */
class AdminNewRegistrationNotification extends Notification
{
    public function __construct(public User $registrant) {}

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
