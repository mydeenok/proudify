<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $otpCode)
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
        return (new MailMessage)
            ->subject('Your Proudify verification code')
            ->greeting("Hi {$notifiable->first_name},")
            ->line('Use the code below to verify your email address. It expires in 10 minutes.')
            ->line(new \Illuminate\Support\HtmlString("<h1 style=\"letter-spacing: 0.2em; text-align: center;\">{$this->otpCode}</h1>"))
            ->line('If you did not request this, you can safely ignore this email.');
    }
}
