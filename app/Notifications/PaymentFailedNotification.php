<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately not ShouldQueue — see PaymentSuccessfulNotification.
 */
class PaymentFailedNotification extends Notification
{
    public function __construct(public UserSubscription $userSubscription) {}

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
            ->subject('Payment failed for your Proudify subscription')
            ->view('emails.payment-failed', ['userSubscription' => $this->userSubscription]);
    }
}
