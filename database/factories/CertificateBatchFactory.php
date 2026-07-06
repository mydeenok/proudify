<?php

namespace Database\Factories;

use App\Models\CertificateBatch;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateBatch>
 */
class CertificateBatchFactory extends Factory
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
            'template_id' => Template::factory(),
            'original_filename' => 'recipients.csv',
            'status' => 'uploaded',
        ];
    }
}
