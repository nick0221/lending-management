<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPaymentAllocation;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Builds the printable loan statement shared by the staff statement and the
 * borrower's own copy. The payload is read-only: it carries no payment
 * reversal ability because a printed statement never offers actions.
 */
class LoanStatementService
{
    /**
     * Assemble everything a statement needs for the given loan.
     *
     * @return array<string, mixed>
     */
    public function build(Loan $loan): array
    {
        $loan->load([
            'borrower',
            'loanProduct',
            'installments.allocations',
            'payments.allocations',
            'payments.recordedBy',
            'payments.reversedBy',
        ]);

        return [
            'organisation' => app(Settings::class)->organisationName(),
            'generated_at' => Date::now()->toIso8601String(),
            'loan' => $this->loanPayload($loan),
            'borrower' => [
                'name' => $loan->borrower->name,
                'phone' => $loan->borrower->phone,
                'email' => $loan->borrower->email,
                'national_id' => $loan->borrower->national_id,
            ],
            'installments' => $this->installmentPayload($loan),
            'payments' => $this->paymentPayload($loan->payments),
            'totals' => $this->paymentTotals($loan),
        ];
    }

    /**
     * The loan terms and status shown in the statement header.
     *
     * @return array<string, mixed>
     */
    private function loanPayload(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'borrower_id' => $loan->borrower_id,
            'borrower_name' => $loan->borrower->name,
            'loan_product_id' => $loan->loan_product_id,
            'loan_product_name' => $loan->loanProduct->name,
            'principal' => $loan->principal,
            'interest_rate' => $loan->interest_rate,
            'interest_method' => $loan->interest_method->value,
            'interest_method_label' => $loan->interest_method->label(),
            'repayment_frequency' => $loan->repayment_frequency->value,
            'repayment_frequency_label' => $loan->repayment_frequency->label(),
            'term_months' => $loan->term_months,
            'processing_fee_rate' => $loan->processing_fee_rate,
            'penalty_rate' => $loan->penalty_rate,
            'grace_period_days' => $loan->grace_period_days,
            'days_past_due' => $loan->days_past_due,
            'status' => $loan->status->value,
            'status_label' => $loan->status->label(),
            'applied_at' => $loan->applied_at?->toIso8601String(),
            'approved_at' => $loan->approved_at?->toIso8601String(),
            'rejected_at' => $loan->rejected_at?->toIso8601String(),
            'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
            'closed_at' => $loan->closed_at?->toIso8601String(),
            'notes' => $loan->notes,
            'created_at' => $loan->created_at?->toIso8601String(),
        ];
    }

    /**
     * The repayment schedule, oldest installment first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function installmentPayload(Loan $loan): array
    {
        return $loan->installments
            ->sortBy('sequence')
            ->map(function (LoanInstallment $installment): array {
                $paid = round(
                    (float) $installment->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->principal_allocated
                        + (float) $allocation->interest_allocated
                        + (float) $allocation->fee_allocated
                        + (float) $allocation->penalty_allocated),
                    2,
                );

                return [
                    'sequence' => $installment->sequence,
                    'due_date' => $installment->due_date->toIso8601String(),
                    'principal_due' => $installment->principal_due,
                    'interest_due' => $installment->interest_due,
                    'fee_due' => $installment->fee_due,
                    'balance' => $installment->balance,
                    'penalty_accrued' => $installment->penalty_accrued,
                    'paid' => number_format($paid, 2, '.', ''),
                    'outstanding' => number_format($installment->outstandingTotal(), 2, '.', ''),
                    'principal_outstanding' => number_format($installment->outstandingPrincipal(), 2, '.', ''),
                    'interest_outstanding' => number_format($installment->outstandingInterest(), 2, '.', ''),
                    'fee_outstanding' => number_format($installment->outstandingFee(), 2, '.', ''),
                    'penalty_outstanding' => number_format($installment->outstandingPenalty(), 2, '.', ''),
                    'overdue' => $installment->isOverdue(),
                    'days_past_due' => $installment->daysPastDue(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The recorded payments, newest first, without any action abilities.
     *
     * @param  Collection<int, Payment>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function paymentPayload(Collection $payments): array
    {
        return $payments
            ->sortByDesc('payment_date')
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'payment_date' => $payment->payment_date->toIso8601String(),
                'recorded_by' => $payment->recordedBy?->name,
                'reversed_at' => $payment->reversed_at?->toIso8601String(),
                'reversal_reason' => $payment->reversal_reason,
                'reversed_by' => $payment->reversedBy?->name,
                'can_reverse' => false,
                'allocations' => $payment->allocations
                    ->map(fn (LoanPaymentAllocation $allocation): array => [
                        'installment_sequence' => $allocation->sequence,
                        'principal_allocated' => $allocation->principal_allocated,
                        'interest_allocated' => $allocation->interest_allocated,
                        'fee_allocated' => $allocation->fee_allocated,
                        'penalty_allocated' => $allocation->penalty_allocated,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Summarise the amounts payable, paid, outstanding and in arrears.
     *
     * @return array<string, mixed>
     */
    private function paymentTotals(Loan $loan): array
    {
        $payable = 0.0;
        $paid = round((float) $loan->payments->whereNull('reversed_at')->sum('amount'), 2);
        $outstanding = 0.0;
        $arrears = 0.0;
        $penalty = 0.0;

        foreach ($loan->installments as $installment) {
            $installmentOutstanding = $installment->outstandingTotal();

            $payable += $installment->dueAmount();
            $outstanding += $installmentOutstanding;
            $penalty += $installment->outstandingPenalty();

            if ($installment->isOverdue()) {
                $arrears += $installmentOutstanding;
            }
        }

        return [
            'payable' => number_format(round($payable, 2), 2, '.', ''),
            'paid' => number_format(round($paid, 2), 2, '.', ''),
            'outstanding' => number_format(round($outstanding, 2), 2, '.', ''),
            'arrears' => number_format(round($arrears, 2), 2, '.', ''),
            'penalty' => number_format(round($penalty, 2), 2, '.', ''),
        ];
    }
}
