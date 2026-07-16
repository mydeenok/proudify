<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately not ShouldQueue — the buyer is watching checkout resolve,
 * so it sends inline. The caller wraps this in a try/catch so a mail
 * failure can't turn a successful payment into a failed response.
 */
class PaymentSuccessfulNotification extends Notification
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
            ->subject('Payment successful — your plan is active')
            ->view('emails.payment-successful', ['userSubscription' => $this->userSubscription]);
    }
}
