<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
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
            ->subject('Your Proudify registration request')
            ->greeting("Hi {$notifiable->first_name},")
            ->line('After review, we are unable to approve your Proudify registration at this time.')
            ->line('If you believe this is a mistake, please contact our support team.');
    }
}
