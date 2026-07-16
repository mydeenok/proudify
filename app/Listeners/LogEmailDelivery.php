<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Only the 'mail' channel is logged here - 'database' channel events fire
 * for the exact same notification instances (each channel is dispatched as
 * its own independent queued job, see NotificationSender::queueNotification)
 * and aren't what "how many emails sent/failed" is asking about.
 */
class LogEmailDelivery
{
    public function handleSent(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        EmailLog::create([
            'notification_class' => get_class($event->notification),
            'recipient_email' => $this->recipientEmail($event->notifiable),
            'status' => 'sent',
        ]);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        EmailLog::create([
            'notification_class' => get_class($event->notification),
            'recipient_email' => $this->recipientEmail($event->notifiable),
            'status' => 'failed',
            'error_message' => $event->data['exception']?->getMessage(),
        ]);
    }

    private function recipientEmail(mixed $notifiable): string
    {
        if (method_exists($notifiable, 'routeNotificationForMail')) {
            $route = $notifiable->routeNotificationForMail();

            if (is_string($route)) {
                return $route;
            }

            if (is_array($route) && $route !== []) {
                return (string) (array_key_exists(0, $route) ? $route[0] : array_key_first($route));
            }
        }

        return (string) ($notifiable->email ?? '');
    }
}
