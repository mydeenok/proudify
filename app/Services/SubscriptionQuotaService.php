<?php

namespace App\Services;

use App\Actions\Subscriptions\ActivateFreePlanAction;
use App\Exceptions\SubscriptionQuotaExceededException;
use App\Models\Certificate;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\AdminQuotaAlmostFullNotification;
use App\Notifications\QuotaAlmostFullNotification;
use App\Support\NotifyAdmins;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns subscription-quota enforcement for certificate issuance. The
 * check-and-consume step runs inside a locked transaction specifically
 * because bulk-upload chunk jobs (Milestone 3) process concurrently —
 * without the lock, two chunks could both read "1 slot remaining" and
 * both proceed, overshooting the limit. Model::increment() alone is
 * atomic for the write, but the read-then-decide step is not.
 */
class SubscriptionQuotaService
{
    public function __construct(private readonly ActivateFreePlanAction $activateFreePlan) {}

    public function resolveUsableSubscription(User $user): UserSubscription
    {
        $active = UserSubscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('payment_status', 'completed')
            ->where('end_date', '>', now())
            ->latest('start_date')
            ->first();

        if ($active) {
            return $active;
        }

        try {
            return $this->activateFreePlan->execute($user);
        } catch (ValidationException) {
            throw SubscriptionQuotaExceededException::noActiveSubscription();
        }
    }

    public function isNewRecipient(User $issuer, string $recipientEmail): bool
    {
        return ! Certificate::where('user_id', $issuer->id)
            ->where('recipient_email', $recipientEmail)
            ->exists();
    }

    private const QUOTA_WARNING_THRESHOLD_PERCENT = 90;

    /**
     * @throws SubscriptionQuotaExceededException
     */
    public function consume(UserSubscription $subscription, bool $isNewRecipient): void
    {
        $crossedWarningThreshold = null;

        DB::transaction(function () use ($subscription, $isNewRecipient, &$crossedWarningThreshold) {
            $locked = UserSubscription::whereKey($subscription->id)->lockForUpdate()->first();

            if ($locked->certificates_used >= $locked->certificates_limit) {
                throw SubscriptionQuotaExceededException::certificates();
            }

            if ($isNewRecipient && $locked->users_used >= $locked->users_limit) {
                throw SubscriptionQuotaExceededException::users();
            }

            $percentBefore = $this->percentOfQuotaUsed($locked);

            $locked->increment('certificates_used');

            if ($isNewRecipient) {
                $locked->increment('users_used');
            }

            // Fire once, right as the threshold is crossed - not on every
            // certificate issued afterward.
            if ($percentBefore < self::QUOTA_WARNING_THRESHOLD_PERCENT
                && $this->percentOfQuotaUsed($locked) >= self::QUOTA_WARNING_THRESHOLD_PERCENT) {
                $crossedWarningThreshold = $locked;
            }
        });

        if ($crossedWarningThreshold) {
            $crossedWarningThreshold->loadMissing('user', 'subscription');
            $percentUsed = $this->percentOfQuotaUsed($crossedWarningThreshold);

            $crossedWarningThreshold->user->notify(new QuotaAlmostFullNotification(
                $crossedWarningThreshold,
                $percentUsed
            ));

            NotifyAdmins::notify(
                new AdminQuotaAlmostFullNotification(
                    $crossedWarningThreshold,
                    $percentUsed
                ),
                'Failed to send admin quota-almost-full alert.',
                ['user_subscription_id' => $crossedWarningThreshold->id],
            );
        }
    }

    private function percentOfQuotaUsed(UserSubscription $subscription): int
    {
        return $subscription->certificates_limit > 0
            ? (int) floor(($subscription->certificates_used / $subscription->certificates_limit) * 100)
            : 100;
    }
}
