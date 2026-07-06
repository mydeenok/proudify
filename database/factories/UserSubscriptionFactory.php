<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSubscription>
 */
class UserSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'certificates_limit' => 50,
            'users_limit' => 50,
            'certificates_used' => 0,
            'users_used' => 0,
            'current_period_started_at' => now(),
            'billing_period' => 'monthly',
            'amount_paid' => 999,
            'currency' => 'INR',
            'payment_status' => 'completed',
            'payment_verified_at' => now(),
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
            'auto_renew' => false,
        ];
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'certificates_used' => $attributes['certificates_limit'] ?? 50,
        ]);
    }
}
