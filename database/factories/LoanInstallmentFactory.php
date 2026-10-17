<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanInstallment>
 */
class LoanInstallmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $principalDue = fake()->randomFloat(2, 100, 5000);

        return [
            'loan_id' => Loan::factory(),
            'sequence' => fake()->unique()->numberBetween(1, 1000),
            'due_date' => fake()->dateTimeBetween('+1 month', '+3 years'),
            'principal_due' => $principalDue,
            'interest_due' => fake()->randomFloat(2, 1, 1000),
            'fee_due' => 0,
            'balance' => fake()->randomFloat(2, 0, 100000),
        ];
    }
}
