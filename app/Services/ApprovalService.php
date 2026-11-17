<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Four-eyes workflow for financially sensitive actions. A request is recorded
 * first and nothing changes on the loan until a different administrator
 * approves it.
 */
class ApprovalService
{
    public function __construct(
        private LoanService $loanService,
        private RepaymentService $repaymentService,
    ) {}

    /**
     * Ask a second administrator to write off the loan.
     */
    public function requestWriteOff(Loan $loan, User $actor, string $reason): ApprovalRequest
    {
        return $this->request($loan, $actor, ApprovalType::LoanWriteOff, $reason);
    }

    /**
     * Ask a second administrator to reverse the payment.
     */
    public function requestPaymentReversal(Loan $loan, Payment $payment, User $actor, string $reason): ApprovalRequest
    {
        return $this->request($loan, $actor, ApprovalType::PaymentReversal, $reason, $payment);
    }

    /**
     * Approve the request and run the action exactly once.
     */
    public function approve(ApprovalRequest $approval, User $actor, ?string $note = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approval, $actor, $note): ApprovalRequest {
            $locked = $this->lockForDecision($approval, $actor);

            $loan = $locked->loan()->firstOrFail();

            match ($locked->type) {
                ApprovalType::LoanWriteOff => $this->loanService->writeOff($loan, $actor),
                ApprovalType::PaymentReversal => $this->repaymentService->reversePayment(
                    $loan,
                    $locked->payment()->firstOrFail(),
                    $actor,
                    $locked->reason,
                ),
            };

            $locked->forceFill([
                'status' => ApprovalStatus::Approved,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            return $locked;
        });
    }

    /**
     * Decline the request without touching the loan.
     */
    public function reject(ApprovalRequest $approval, User $actor, ?string $note = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approval, $actor, $note): ApprovalRequest {
            $locked = $this->lockForDecision($approval, $actor);

            $locked->forceFill([
                'status' => ApprovalStatus::Rejected,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            return $locked;
        });
    }

    /**
     * Record a pending request, refusing a second request for the same action.
     */
    private function request(
        Loan $loan,
        User $actor,
        ApprovalType $type,
        string $reason,
        ?Payment $payment = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($loan, $actor, $type, $reason, $payment): ApprovalRequest {
            Loan::query()
                ->whereKey($loan->getKey())
                ->lockForUpdate()
                ->first();

            $alreadyPending = ApprovalRequest::query()
                ->where('type', $type)
                ->where('status', ApprovalStatus::Pending)
                ->when(
                    $payment !== null,
                    fn ($query) => $query->where('payment_id', $payment->getKey()),
                    fn ($query) => $query->where('loan_id', $loan->getKey()),
                )
                ->exists();

            if ($alreadyPending) {
                throw ValidationException::withMessages([
                    'approval' => $payment !== null
                        ? __('This payment already has a reversal awaiting approval.')
                        : __('This loan already has a write-off awaiting approval.'),
                ]);
            }

            return ApprovalRequest::create([
                'type' => $type,
                'status' => ApprovalStatus::Pending,
                'loan_id' => $loan->id,
                'payment_id' => $payment?->id,
                'amount' => $this->amountAtStake($loan, $type, $payment),
                'reason' => $reason,
                'requested_by' => $actor->id,
            ]);
        });
    }

    /**
     * Load the request under a row lock and apply the four-eyes rule.
     */
    private function lockForDecision(ApprovalRequest $approval, User $actor): ApprovalRequest
    {
        $locked = ApprovalRequest::query()
            ->whereKey($approval->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if (! $locked->isPending()) {
            throw ValidationException::withMessages([
                'approval' => __('This request has already been decided.'),
            ]);
        }

        if ((int) $locked->requested_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'approval' => __('You cannot decide your own request.'),
            ]);
        }

        return $locked;
    }

    /**
     * Snapshot the amount at stake so the queue does not need to recalculate it.
     */
    private function amountAtStake(Loan $loan, ApprovalType $type, ?Payment $payment): float
    {
        if ($type === ApprovalType::PaymentReversal && $payment !== null) {
            return round((float) $payment->amount, 2);
        }

        return round(
            $loan->installments()
                ->with('allocations')
                ->get()
                ->sum(fn (LoanInstallment $installment): float => $installment->outstandingTotal()),
            2,
        );
    }
}
