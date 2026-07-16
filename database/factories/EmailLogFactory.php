<?php

namespace Database\Factories;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notification_class' => 'App\\Notifications\\AccountApprovedNotification',
            'recipient_email' => fake()->safeEmail(),
            'status' => 'sent',
            'error_message' => null,
        ];
    }
}
