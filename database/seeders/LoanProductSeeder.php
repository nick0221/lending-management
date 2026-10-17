<?php

namespace Database\Seeders;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class LoanProductSeeder extends Seeder
{
    /**
     * Seed the application's loan products.
     */
    public function run(): void
    {
        LoanProduct::factory()->create([
            'name' => 'Personal Loan',
            'description' => 'Unsecured personal loan repaid monthly at a flat interest rate.',
            'interest_method' => InterestMethod::Flat->value,
            'interest_rate' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly->value,
            'min_amount' => 1000,
            'max_amount' => 50000,
            'min_term_months' => 6,
            'max_term_months' => 48,
            'processing_fee_rate' => 2,
            'penalty_rate' => 2,
            'grace_period_days' => 5,
            'is_active' => true,
        ]);

        LoanProduct::factory()->decliningBalance()->create([
            'name' => 'Business Loan',
            'description' => 'Secured business loan repaid monthly on a declining balance.',
            'interest_rate' => 18,
            'repayment_frequency' => RepaymentFrequency::Monthly->value,
            'min_amount' => 5000,
            'max_amount' => 250000,
            'min_term_months' => 12,
            'max_term_months' => 60,
            'processing_fee_rate' => 1.5,
            'penalty_rate' => 1.5,
            'grace_period_days' => 7,
            'is_active' => true,
        ]);

        LoanProduct::factory()->weekly()->create([
            'name' => 'Micro Loan',
            'description' => 'Short-term micro loan repaid weekly.',
            'interest_rate' => 24,
            'repayment_frequency' => RepaymentFrequency::Weekly->value,
            'min_amount' => 200,
            'max_amount' => 5000,
            'min_term_months' => 1,
            'max_term_months' => 12,
            'processing_fee_rate' => 3,
            'penalty_rate' => 3,
            'grace_period_days' => 3,
            'is_active' => true,
        ]);
    }
}
