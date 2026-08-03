<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminQuotaAlmostFullNotification extends Notification
{
    public function __construct(
        public UserSubscription $userSubscription,
        public int $percentUsed,
    ) {}

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
            ->subject('Tenant certificate quota nearly full')
            ->view('emails.admin-quota-almost-full', [
                'userSubscription' => $this->userSubscription,
                'percentUsed' => $this->percentUsed,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $user = $this->userSubscription->user;
        $plan = $this->userSubscription->subscription?->name ?? 'plan';

        return [
            'title' => 'Tenant quota nearly full',
            'body' => ($user?->organization_name ?? $user?->name ?? 'A tenant')." hit {$this->percentUsed}% of certificate quota on {$plan}.",
            'route' => 'admin.user-subscriptions.index',
            'route_params' => [],
        ];
    }
}
