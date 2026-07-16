<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNewPurchaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public UserSubscription $userSubscription)
    {
        $this->onQueue(config('certificates.queues.mail'));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New subscription purchase')
            ->view('emails.admin-new-purchase', ['userSubscription' => $this->userSubscription]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $amount = number_format((float) $this->userSubscription->amount_paid, 2);

        return [
            'title' => 'New subscription purchase',
            'body' => "{$this->userSubscription->user->organization_name} purchased the {$this->userSubscription->subscription->name} plan ({$this->userSubscription->currency} {$amount}).",
            'route' => 'admin.user-subscriptions.index',
            'route_params' => [],
        ];
    }
}
