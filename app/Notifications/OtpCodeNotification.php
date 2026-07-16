<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately not ShouldQueue — the user is actively waiting on this
 * code, so it sends inline in the request rather than depending on a
 * queue worker being up.
 */
class OtpCodeNotification extends Notification
{
    public function __construct(private readonly string $otpCode) {}

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
            ->subject('Your Proudify verification code')
            ->view('emails.otp-code', ['user' => $notifiable, 'otpCode' => $this->otpCode]);
    }
}
