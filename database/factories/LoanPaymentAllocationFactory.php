<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPaymentAllocation;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanPaymentAllocation>
 */
class LoanPaymentAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'loan_id' => Loan::factory(),
            'installment_id' => LoanInstallment::factory(),
            'principal_allocated' => 1000,
            'interest_allocated' => 0,
            'fee_allocated' => 0,
        ];
    }
}
