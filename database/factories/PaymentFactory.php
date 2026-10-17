<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'installment_id' => null,
            'amount' => 1000,
            'method' => PaymentMethod::BankTransfer->value,
            'reference' => fake()->bothify('PAY-####??'),
            'notes' => fake()->optional()->sentence(),
            'payment_date' => now(),
            'recorded_by' => User::factory(),
        ];
    }
}
