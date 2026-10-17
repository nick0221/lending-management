<?php

namespace Database\Factories;

use App\Enums\DisbursementMethod;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disbursement>
 */
class DisbursementFactory extends Factory
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
            'amount' => 10000,
            'method' => DisbursementMethod::BankTransfer->value,
            'reference' => fake()->bothify('DSB-####??'),
            'disbursed_at' => now(),
            'recorded_by' => User::factory(),
        ];
    }
}
