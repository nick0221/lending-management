<?php

namespace Database\Factories;

use App\Enums\KycStatus;
use App\Models\Borrower;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Borrower>
 */
class BorrowerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'national_id' => fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('+1-###-###-####'),
            'email' => fake()->unique()->safeEmail(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'address' => fake()->address(),
            'kyc_status' => KycStatus::Pending->value,
            'notes' => null,
            'notify_due_soon' => true,
            'notify_overdue' => true,
            'notify_receipts' => true,
            'notify_statements' => true,
        ];
    }

    /**
     * Indicate that the borrower has been verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'kyc_status' => KycStatus::Verified->value,
        ]);
    }

    /**
     * Indicate that the borrower has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'kyc_status' => KycStatus::Rejected->value,
        ]);
    }

    /**
     * Indicate that the borrower is linked to the given user account.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
