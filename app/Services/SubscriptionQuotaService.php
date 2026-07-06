<?php

namespace App\Services;

use App\Actions\Subscriptions\ActivateFreePlanAction;
use App\Exceptions\SubscriptionQuotaExceededException;
use App\Models\Certificate;
use App\Models\User;
use App\Models\UserSubscription;
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

    /**
     * @throws SubscriptionQuotaExceededException
     */
    public function consume(UserSubscription $subscription, bool $isNewRecipient): void
    {
        DB::transaction(function () use ($subscription, $isNewRecipient) {
            $locked = UserSubscription::whereKey($subscription->id)->lockForUpdate()->first();

            if ($locked->certificates_used >= $locked->certificates_limit) {
                throw SubscriptionQuotaExceededException::certificates();
            }

            if ($isNewRecipient && $locked->users_used >= $locked->users_limit) {
                throw SubscriptionQuotaExceededException::users();
            }

            $locked->increment('certificates_used');

            if ($isNewRecipient) {
                $locked->increment('users_used');
            }
        });
    }
}
