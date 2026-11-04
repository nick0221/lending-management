<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPaymentAllocation;
use App\Models\Payment;
use App\Services\LoanStatementService;
use App\Services\PaymentReceiptService;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BorrowerLoanController extends Controller
{
    /**
     * Show one of the signed-in borrower's loans with its schedule and
     * payments. Staff use the administration loan file instead.
     */
    public function show(Request $request, Loan $loan): Response
    {
        Gate::authorize('viewOwn', $loan);

        $loan->load([
            'loanProduct',
            'installments.allocations',
            'payments.allocations',
            'payments.recordedBy',
            'payments.reversedBy',
        ]);

        $installments = $this->installmentPayload($loan);

        return Inertia::render('loans/show', [
            'loan' => $this->loanPayload($loan),
            'summary' => $this->summary($loan, $installments),
            'installments' => $installments,
            'payments' => $this->paymentPayload($loan->payments),
        ]);
    }

    /**
     * Show the borrower a printable statement of their own loan.
     */
    public function statement(Loan $loan, LoanStatementService $statementService): Response
    {
        Gate::authorize('viewOwn', $loan);

        return Inertia::render(
            'loans/statement',
            $statementService->build($loan),
        );
    }

    /**
     * Show the borrower a printable receipt for one of their payments.
     */
    public function receipt(Loan $loan, Payment $payment, PaymentReceiptService $receiptService): Response
    {
        Gate::authorize('viewOwn', $loan);

        abort_unless((int) $payment->loan_id === (int) $loan->id, 404);

        return Inertia::render('loans/receipt', [
            'organisation' => app(Settings::class)->organisationName(),
            'receipt' => $receiptService->build($payment),
        ]);
    }

    /**
     * The loan terms and status shown at the top of the page.
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
     * The figures summarising the loan for the borrower.
     *
     * @param  array<int, array<string, mixed>>  $installments
     * @return array<string, mixed>
     */
    private function summary(Loan $loan, array $installments): array
    {
        $payable = round($loan->installments->sum(
            fn (LoanInstallment $installment): float => (float) $installment->principal_due
                + (float) $installment->interest_due
                + (float) $installment->fee_due
                + (float) $installment->penalty_accrued,
        ), 2);

        $outstanding = round($loan->installments->sum(
            fn (LoanInstallment $installment): float => $installment->outstandingTotal(),
        ), 2);

        $arrears = round($loan->installments->sum(
            fn (LoanInstallment $installment): float => $installment->isOverdue()
                ? $installment->outstandingTotal()
                : 0.0,
        ), 2);

        $next = collect($installments)->first(
            fn (array $installment): bool => (float) $installment['outstanding'] > 0,
        );

        return [
            'payable' => number_format($payable, 2, '.', ''),
            'paid' => number_format($payable - $outstanding, 2, '.', ''),
            'outstanding' => number_format($outstanding, 2, '.', ''),
            'arrears' => number_format($arrears, 2, '.', ''),
            'next_due' => $next === null ? null : [
                'sequence' => $next['sequence'],
                'due_date' => $next['due_date'],
                'amount' => $next['outstanding'],
                'overdue' => $next['overdue'],
                'days_past_due' => $next['days_past_due'],
            ],
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
                    (float) $installment->allocations->sum(
                        fn (LoanPaymentAllocation $allocation): float => (float) $allocation->principal_allocated
                            + (float) $allocation->interest_allocated
                            + (float) $allocation->fee_allocated
                            + (float) $allocation->penalty_allocated,
                    ),
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
     * The borrower's payments, newest first, with where the money went.
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
}
