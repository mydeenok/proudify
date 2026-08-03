<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPaymentFailedNotification extends Notification
{
    public function __construct(public UserSubscription $userSubscription) {}

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
            ->subject('Payment failed for a subscription purchase')
            ->view('emails.admin-payment-failed', ['userSubscription' => $this->userSubscription]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $user = $this->userSubscription->user;
        $plan = $this->userSubscription->subscription?->name ?? 'plan';

        return [
            'title' => 'Payment failed',
            'body' => ($user?->organization_name ?? $user?->name ?? 'A tenant')." failed to pay for {$plan}.",
            'route' => 'admin.user-subscriptions.index',
            'route_params' => [],
        ];
    }
}
