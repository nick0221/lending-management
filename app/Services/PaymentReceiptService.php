<?php

namespace App\Services;

use App\Models\LoanPaymentAllocation;
use App\Models\Payment;

/**
 * Builds the printable receipt payload shared by the staff receipt page and
 * the borrower's own copy.
 */
class PaymentReceiptService
{
    /**
     * Assemble everything a receipt needs for the given payment.
     *
     * @return array<string, mixed>
     */
    public function build(Payment $payment): array
    {
        $payment->loadMissing([
            'loan.borrower',
            'loan.loanProduct',
            'allocations',
            'recordedBy',
            'reversedBy',
        ]);

        return [
            'id' => $payment->id,
            'number' => 'R-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            'payment_date' => $payment->payment_date->toDateString(),
            'amount' => $payment->amount,
            'method_label' => $payment->method->label(),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'recorded_by' => $payment->recordedBy?->name,
            'reversed_at' => $payment->reversed_at?->toIso8601String(),
            'reversal_reason' => $payment->reversal_reason,
            'reversed_by' => $payment->reversedBy?->name,
            'borrower_name' => $payment->loan->borrower->name,
            'borrower_email' => $payment->loan->borrower->email,
            'loan_id' => $payment->loan_id,
            'product_name' => $payment->loan->loanProduct->name,
            'allocations' => $this->allocations($payment),
        ];
    }

    /**
     * Transform a payment's allocations into the payload shared by the loan
     * file and the receipt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allocations(Payment $payment): array
    {
        return $payment->allocations
            ->map(fn (LoanPaymentAllocation $allocation): array => [
                'installment_sequence' => $allocation->sequence,
                'principal_allocated' => $allocation->principal_allocated,
                'interest_allocated' => $allocation->interest_allocated,
                'fee_allocated' => $allocation->fee_allocated,
                'penalty_allocated' => $allocation->penalty_allocated,
            ])
            ->values()
            ->all();
    }
}
