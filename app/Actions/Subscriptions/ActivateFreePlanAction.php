<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Validation\ValidationException;

/**
 * Free-plan activation is a one-click, no-payment-gateway path — but still
 * a resource with real limits, so it's claimable only once per user, ever.
 */
class ActivateFreePlanAction
{
    public function execute(User $user): UserSubscription
    {
        $freePlan = Subscription::active()->where('is_default_free_plan', true)->first();

        if (! $freePlan) {
            throw ValidationException::withMessages(['plan' => 'No free plan is currently available.']);
        }

        $alreadyClaimed = UserSubscription::withTrashed()
            ->where('user_id', $user->id)
            ->where('subscription_id', $freePlan->id)
            ->exists();

        if ($alreadyClaimed) {
            throw ValidationException::withMessages(['plan' => 'You have already used your free plan.']);
        }

        $limits = $freePlan->limitsFor('monthly');
        $now = now();

        return UserSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => $freePlan->id,
            'certificates_limit' => $limits['certificates'],
            'users_limit' => $limits['users'],
            'current_period_started_at' => $now,
            'billing_period' => 'monthly',
            'amount_paid' => 0,
            'currency' => 'INR',
            'payment_status' => 'completed',
            'payment_verified_at' => $now,
            'start_date' => $now,
            'end_date' => $now->copy()->addMonth(),
            'is_active' => true,
            'auto_renew' => true,
        ]);
    }
}
