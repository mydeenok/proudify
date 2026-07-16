<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately not ShouldQueue — an admin is watching this action resolve
 * ("Approved" toast), so it sends inline rather than depending on a queue
 * worker being up. The caller wraps this in a try/catch so a mail failure
 * can't turn a successful approval into a 500.
 */
class AccountApprovedNotification extends Notification
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
            ->subject('Your Proudify account is approved')
            ->view('emails.account-approved', ['user' => $notifiable]);
    }
}
