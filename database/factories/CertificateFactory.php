<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Template;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $verificationService = app(VerificationService::class);
        $uuid = (string) Str::uuid();
        $code = $verificationService->generateCode();
        $dateOfIssue = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

        return [
            'uuid' => $uuid,
            'verification_code' => $code,
            'verification_signature' => $verificationService->sign($uuid, $code, $dateOfIssue),
            'user_id' => User::factory(),
            'template_id' => Template::factory(),
            'title' => 'Certificate of Achievement',
            'recipient_name' => fake()->name(),
            'recipient_email' => fake()->safeEmail(),
            'description' => fake()->sentence(),
            'date_of_issue' => $dateOfIssue,
            'date_of_expiry' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_reason' => 'Test revocation',
        ]);
    }

    public function expired(): static
    {
        // Overriding date_of_issue changes the value the signature is
        // bound to, so it must be recomputed here or verification will
        // (correctly, but confusingly for a test) reject the row.
        return $this->state(function (array $attributes) {
            $dateOfIssue = now()->subYears(2)->toDateString();
            $verificationService = app(VerificationService::class);

            return [
                'date_of_issue' => $dateOfIssue,
                'date_of_expiry' => now()->subYear()->toDateString(),
                'verification_signature' => $verificationService->sign(
                    $attributes['uuid'],
                    $attributes['verification_code'],
                    $dateOfIssue
                ),
            ];
        });
    }
}
