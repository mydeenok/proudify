<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately not ShouldQueue — see AccountApprovedNotification.
 */
class AccountRejectedNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Proudify registration request')
            ->view('emails.account-rejected', ['user' => $notifiable]);
    }
}
