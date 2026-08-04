<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminSubscriptionExpiredNotification extends Notification
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
            ->subject('A user subscription has expired')
            ->view('emails.admin-subscription-expired', ['userSubscription' => $this->userSubscription]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $user = $this->userSubscription->user;
        $plan = $this->userSubscription->subscription?->name ?? 'plan';

        return [
            'title' => 'Subscription expired',
            'body' => ($user?->organization_name ?? $user?->name ?? 'A tenant')."'s {$plan} subscription expired.",
            'route' => 'admin.user-subscriptions.index',
            'route_params' => [],
        ];
    }
}
