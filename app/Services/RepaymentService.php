<?php

namespace App\Services;

use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\LoanInstallment;
use App\Models\LoanPaymentAllocation;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepaymentService
{
    /**
     * Tolerance (half a cent) used when comparing monetary amounts.
     */
    private const MONEY_EPSILON = 0.005;

    public function __construct(private LoanService $loanService) {}

    /**
     * Record a payment against a loan, allocating it to the outstanding
     * installments oldest-first (penalty, then fee, interest and principal) and
     * closing the loan once it is fully repaid.
     */
    public function recordPayment(
        Loan $loan,
        User $actor,
        float $amount,
        PaymentMethod $method,
        ?string $reference = null,
        ?string $notes = null,
        ?CarbonInterface $paymentDate = null,
    ): Payment {
        if ($loan->status !== LoanStatus::Active) {
            throw ValidationException::withMessages([
                'loan' => __('Payments can only be recorded against active loans.'),
            ]);
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('The payment amount must be greater than zero.'),
            ]);
        }

        $targets = $this->outstandingTargets($loan);
        $totalOutstanding = round(
            collect($targets)->sum(fn (array $target): float => $target['penalty'] + $target['fee'] + $target['interest'] + $target['principal']),
            2,
        );

        if ($amount - $totalOutstanding > self::MONEY_EPSILON) {
            throw ValidationException::withMessages([
                'amount' => __('The payment amount exceeds the outstanding balance of :amount.', [
                    'amount' => number_format($totalOutstanding, 2, '.', ''),
                ]),
            ]);
        }

        $payment = DB::transaction(function () use ($loan, $actor, $amount, $method, $reference, $notes, $paymentDate, $targets): Payment {
            $payment = $loan->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'notes' => $notes,
                'payment_date' => $paymentDate?->toDateTimeString() ?? now(),
                'recorded_by' => $actor->id,
            ]);

            $remaining = $amount;

            foreach ($targets as $target) {
                if ($remaining <= self::MONEY_EPSILON) {
                    break;
                }

                $penalty = min($remaining, $target['penalty']);
                $remaining = round($remaining - $penalty, 2);

                $fee = min($remaining, $target['fee']);
                $remaining = round($remaining - $fee, 2);

                $interest = min($remaining, $target['interest']);
                $remaining = round($remaining - $interest, 2);

                $principal = min($remaining, $target['principal']);
                $remaining = round($remaining - $principal, 2);

                if ($penalty <= 0 && $fee <= 0 && $interest <= 0 && $principal <= 0) {
                    continue;
                }

                $payment->allocations()->create([
                    'loan_id' => $loan->id,
                    'installment_id' => $target['installment']->id,
                    'sequence' => $target['installment']->sequence,
                    'penalty_allocated' => $penalty,
                    'fee_allocated' => $fee,
                    'interest_allocated' => $interest,
                    'principal_allocated' => $principal,
                ]);
            }

            return $payment;
        });

        LoanEvent::create([
            'loan_id' => $loan->id,
            'user_id' => $actor->id,
            'action' => LoanEventType::PaymentRecorded,
            'from_status' => LoanStatus::Active,
            'to_status' => LoanStatus::Active,
        ]);

        if (round($totalOutstanding - $amount, 2) <= self::MONEY_EPSILON) {
            $this->loanService->transition($loan, LoanStatus::Closed, $actor);
        }

        return $payment;
    }

    /**
     * Determine what is still owed on each installment, oldest sequence first.
     *
     * @return array<int, array{installment: LoanInstallment, penalty: float, fee: float, interest: float, principal: float}>
     */
    private function outstandingTargets(Loan $loan): array
    {
        return $loan->installments()
            ->with('allocations')
            ->orderBy('sequence')
            ->get()
            ->map(function (LoanInstallment $installment): array {
                $penaltyPaid = round($installment->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->penalty_allocated), 2);
                $feePaid = round($installment->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->fee_allocated), 2);
                $interestPaid = round($installment->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->interest_allocated), 2);
                $principalPaid = round($installment->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->principal_allocated), 2);

                return [
                    'installment' => $installment,
                    'penalty' => max(round((float) $installment->penalty_accrued - $penaltyPaid, 2), 0.0),
                    'fee' => max(round((float) $installment->fee_due - $feePaid, 2), 0.0),
                    'interest' => max(round((float) $installment->interest_due - $interestPaid, 2), 0.0),
                    'principal' => max(round((float) $installment->principal_due - $principalPaid, 2), 0.0),
                ];
            })
            ->all();
    }
}
