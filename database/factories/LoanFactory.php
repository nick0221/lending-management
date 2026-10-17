<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $loanProduct = LoanProduct::factory()->create();

        return [
            'borrower_id' => Borrower::factory(),
            'loan_product_id' => $loanProduct->id,
            'principal' => 10000,
            'interest_rate' => $loanProduct->interest_rate,
            'interest_method' => $loanProduct->interest_method->value,
            'repayment_frequency' => $loanProduct->repayment_frequency->value,
            'term_months' => 12,
            'processing_fee_rate' => $loanProduct->processing_fee_rate,
            'penalty_rate' => $loanProduct->penalty_rate,
            'grace_period_days' => $loanProduct->grace_period_days,
            'status' => LoanStatus::Pending->value,
            'applied_at' => now(),
            'notes' => null,
        ];
    }

    /**
     * Snapshot the terms of the given product onto the loan.
     */
    public function forProduct(LoanProduct $loanProduct): static
    {
        return $this->state(fn (array $attributes): array => [
            'loan_product_id' => $loanProduct->id,
            'interest_rate' => $loanProduct->interest_rate,
            'interest_method' => $loanProduct->interest_method->value,
            'repayment_frequency' => $loanProduct->repayment_frequency->value,
            'processing_fee_rate' => $loanProduct->processing_fee_rate,
            'penalty_rate' => $loanProduct->penalty_rate,
            'grace_period_days' => $loanProduct->grace_period_days,
        ]);
    }

    /**
     * Indicate that the loan is awaiting review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LoanStatus::Pending->value,
            'applied_at' => now(),
        ]);
    }

    /**
     * Indicate that the loan has been approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LoanStatus::Approved->value,
            'applied_at' => now()->subDay(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the loan has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LoanStatus::Rejected->value,
            'applied_at' => now()->subDay(),
            'rejected_at' => now(),
        ]);
    }

    /**
     * Indicate that the loan has the given status.
     */
    public function withStatus(LoanStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status->value,
        ]);
    }
}
