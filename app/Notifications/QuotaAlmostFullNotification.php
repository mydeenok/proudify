<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotaAlmostFullNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public UserSubscription $userSubscription, public int $percentUsed)
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
            ->subject("You've used {$this->percentUsed}% of your certificate quota")
            ->view('emails.quota-almost-full', [
                'userSubscription' => $this->userSubscription,
                'percentUsed' => $this->percentUsed,
            ]);
    }
}
