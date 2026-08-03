<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fan-out helper for mandatory admin alerts. Each admin is notified inside
 * its own try/catch so one failure can't skip the rest.
 *
 * Database is delivered before mail so the in-app bell still updates when
 * SMTP is misconfigured or unreachable.
 */
class NotifyAdmins
{
    public static function each(Closure $callback, string $failureLogMessage, array $context = []): void
    {
        User::admins()->get()->each(function (User $admin) use ($callback, $failureLogMessage, $context) {
            try {
                $callback($admin);
            } catch (Throwable $exception) {
                Log::error($failureLogMessage, [
                    ...$context,
                    'admin_id' => $admin->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    /**
     * Prefer this for admin alerts that use both database + mail channels.
     */
    public static function notify(Notification $notification, string $failureLogMessage, array $context = []): void
    {
        User::admins()->get()->each(function (User $admin) use ($notification, $failureLogMessage, $context) {
            try {
                $admin->notifyNow($notification, ['database']);
            } catch (Throwable $exception) {
                Log::error($failureLogMessage, [
                    ...$context,
                    'admin_id' => $admin->id,
                    'channel' => 'database',
                    'exception' => $exception->getMessage(),
                ]);

                return;
            }

            try {
                $admin->notifyNow($notification, ['mail']);
            } catch (Throwable $exception) {
                Log::error($failureLogMessage, [
                    ...$context,
                    'admin_id' => $admin->id,
                    'channel' => 'mail',
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
