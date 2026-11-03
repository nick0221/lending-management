<?php

namespace Database\Factories;

use App\Enums\CollectionChannel;
use App\Enums\CollectionOutcome;
use App\Models\CollectionContact;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionContact>
 */
class CollectionContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'user_id' => User::factory(),
            'channel' => CollectionChannel::Call->value,
            'outcome' => CollectionOutcome::Contacted->value,
            'contacted_at' => now(),
            'notes' => fake()->optional()->sentence(),
            'promised_amount' => null,
            'promised_at' => null,
            'follow_up_at' => null,
        ];
    }

    /**
     * Indicate that the borrower promised to pay.
     */
    public function promised(?string $amount = null, ?string $date = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcome' => CollectionOutcome::Promised->value,
            'promised_amount' => $amount ?? '1000.00',
            'promised_at' => $date ?? today()->addWeek()->toDateString(),
        ]);
    }
}
