<?php

namespace Database\Factories;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanProduct>
 */
class LoanProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'interest_method' => InterestMethod::Flat->value,
            'interest_rate' => fake()->randomFloat(2, 5, 30),
            'repayment_frequency' => RepaymentFrequency::Monthly->value,
            'min_amount' => 1000,
            'max_amount' => 100000,
            'min_term_months' => 3,
            'max_term_months' => 36,
            'processing_fee_rate' => fake()->randomFloat(2, 0, 5),
            'penalty_rate' => 0,
            'grace_period_days' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the product uses declining balance interest.
     */
    public function decliningBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'interest_method' => InterestMethod::DecliningBalance->value,
        ]);
    }

    /**
     * Indicate that the product is not active.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the product charges a monthly penalty after a grace period.
     */
    public function withPenalty(float $ratePerMonth, int $gracePeriodDays = 0): static
    {
        return $this->state(fn (array $attributes): array => [
            'penalty_rate' => $ratePerMonth,
            'grace_period_days' => $gracePeriodDays,
        ]);
    }

    /**
     * Indicate that the product is repaid weekly.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'repayment_frequency' => RepaymentFrequency::Weekly->value,
        ]);
    }
}
