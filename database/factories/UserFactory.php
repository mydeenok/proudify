<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'organization_name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'user',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model should be an active platform admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the user has registered but not yet verified their OTP.
     */
    public function pendingOtp(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_otp',
            'otp_code' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(10),
        ]);
    }

    /**
     * Indicate that the user verified their OTP but awaits admin approval.
     */
    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_approval',
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);
    }
}
