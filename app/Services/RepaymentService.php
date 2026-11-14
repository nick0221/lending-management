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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepaymentService
{
    /**
     * Tolerance (half a cent) used when comparing monetary amounts.
     */
    private const MONEY_EPSILON = 0.005;

    public function __construct(
        private LoanService $loanService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Record a payment against a loan, allocating it to the outstanding
     * installments oldest-first (penalty, then fee, interest and principal) and
     * closing the loan once it is fully repaid.
     *
     * A repeated submission carrying the same idempotency key returns the
     * payment recorded the first time, so a double click or retried request
     * cannot record the same money twice (even once the loan is closed).
     */
    public function recordPayment(
        Loan $loan,
        User $actor,
        float $amount,
        PaymentMethod $method,
        ?string $reference = null,
        ?string $notes = null,
        ?CarbonInterface $paymentDate = null,
        ?string $idempotencyKey = null,
    ): Payment {
        $reference = $this->normaliseOptionalString($reference);
        $idempotencyKey = $this->normaliseOptionalString($idempotencyKey);

        $existingPayment = $this->findIdempotentPayment($loan, $idempotencyKey);

        if ($existingPayment !== null) {
            return $existingPayment;
        }

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

        try {
            $payment = DB::transaction(function () use ($loan, $actor, $amount, $method, $reference, $notes, $paymentDate, $idempotencyKey, $targets): Payment {
                Loan::query()
                    ->whereKey($loan->getKey())
                    ->lockForUpdate()
                    ->first();

                $existingPayment = $this->findIdempotentPayment($loan, $idempotencyKey);

                if ($existingPayment !== null) {
                    return $existingPayment;
                }

                if ($reference !== null && $loan->payments()->where('reference', $reference)->whereNull('reversed_at')->exists()) {
                    throw ValidationException::withMessages([
                        'reference' => __('A live payment on this loan already uses this reference.'),
                    ]);
                }

                $payment = $loan->payments()->create([
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'idempotency_key' => $idempotencyKey,
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
        } catch (UniqueConstraintViolationException $exception) {
            $existingPayment = $this->findIdempotentPayment($loan, $idempotencyKey);

            if ($existingPayment === null) {
                throw $exception;
            }

            $payment = $existingPayment;
        }

        if (! $payment->wasRecentlyCreated) {
            return $payment;
        }

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

        $this->notificationService->sendReceipt(
            $payment,
            max(round($totalOutstanding - $amount, 2), 0.0),
        );

        return $payment;
    }

    /**
     * Reverse a payment, release its allocations and reopen a loan that was
     * closed by the payment. The payment row remains as an audit record.
     */
    public function reversePayment(Loan $loan, Payment $payment, User $actor, string $reason): Payment
    {
        DB::transaction(function () use ($loan, $payment, $actor, $reason): void {
            $lockedLoan = Loan::query()
                ->whereKey($loan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedPayment->loan_id !== (int) $lockedLoan->id) {
                throw ValidationException::withMessages([
                    'payment' => __('This payment does not belong to the loan.'),
                ]);
            }

            if ($lockedPayment->reversed_at !== null) {
                throw ValidationException::withMessages([
                    'payment' => __('This payment has already been reversed.'),
                ]);
            }

            if (! in_array($lockedLoan->status, [LoanStatus::Active, LoanStatus::Closed], true)) {
                throw ValidationException::withMessages([
                    'loan' => __('Payments can only be reversed on active or closed loans.'),
                ]);
            }

            $fromStatus = $lockedLoan->status;

            $lockedPayment->allocations()->delete();

            $lockedPayment->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_reason' => $reason,
            ])->save();

            if ($fromStatus === LoanStatus::Closed) {
                $lockedLoan->status = LoanStatus::Active;
                $lockedLoan->closed_at = null;
                $lockedLoan->save();
            }

            LoanEvent::create([
                'loan_id' => $lockedLoan->id,
                'user_id' => $actor->id,
                'action' => LoanEventType::PaymentReversed,
                'from_status' => $fromStatus,
                'to_status' => $lockedLoan->status,
            ]);
        });

        return $payment->refresh();
    }

    /**
     * Find the payment recorded by an earlier submission of the same form.
     * A key presented against a different loan is treated as invalid rather
     * than silently recording nothing.
     */
    private function findIdempotentPayment(Loan $loan, ?string $idempotencyKey): ?Payment
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $payment = Payment::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($payment === null) {
            return null;
        }

        if ((int) $payment->loan_id !== (int) $loan->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('This payment submission was already used for another loan.'),
            ]);
        }

        return $payment;
    }

    private function normaliseOptionalString(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
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
