<?php

namespace Database\Factories;

use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    protected $model = ContactRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'organization' => fake()->company(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'status' => ContactRequest::STATUS_OPEN,
        ];
    }

    public function fromUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'organization' => $user->organization_name,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => ContactRequest::STATUS_CLOSED,
            'handled_at' => now(),
        ]);
    }
}
