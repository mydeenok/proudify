<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One row per real email delivery attempt (the 'mail' notification channel
 * only - database-channel entries are a separate concern, tracked by the
 * notifications table itself). Written by App\Listeners\LogEmailDelivery,
 * which listens for Laravel's own NotificationSent/NotificationFailed
 * events rather than guessing from generic Mail events, so this reflects
 * what actually happened at the transport level - not just what the app
 * attempted.
 */
class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_class',
        'recipient_email',
        'status',
        'error_message',
    ];

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function getNotificationLabelAttribute(): string
    {
        return Str::headline(class_basename($this->notification_class));
    }
}
