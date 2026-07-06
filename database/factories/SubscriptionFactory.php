<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Plan',
            'certificates_per_month' => 50,
            'certificates_per_year' => 600,
            'users_per_month' => 50,
            'users_per_year' => 600,
            'cost_month_inr' => 999,
            'cost_year_inr' => 9999,
            'cost_month_usd' => 19,
            'cost_year_usd' => 199,
            'is_default_free_plan' => false,
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'name' => 'Free',
            'certificates_per_month' => 10,
            'certificates_per_year' => 120,
            'users_per_month' => 10,
            'users_per_year' => 120,
            'cost_month_inr' => 0,
            'cost_year_inr' => 0,
            'cost_month_usd' => 0,
            'cost_year_usd' => 0,
            'is_default_free_plan' => true,
        ]);
    }
}
