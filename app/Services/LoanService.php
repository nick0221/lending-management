<?php

namespace App\Services;

use App\Enums\DisbursementMethod;
use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\LoanInstallment;
use App\Models\LoanPaymentAllocation;
use App\Models\LoanProduct;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(private ScheduleService $scheduleService) {}

    /**
     * Originate a loan from a product, snapshotting the product's terms so
     * later edits to the product do not change existing loans.
     */
    public function originate(Borrower $borrower, LoanProduct $loanProduct, float $principal, int $termMonths, ?string $notes = null, ?User $actor = null): Loan
    {
        $loan = Loan::create([
            'borrower_id' => $borrower->id,
            'loan_product_id' => $loanProduct->id,
            'principal' => $principal,
            'interest_rate' => $loanProduct->interest_rate,
            'interest_method' => $loanProduct->interest_method,
            'repayment_frequency' => $loanProduct->repayment_frequency,
            'term_months' => $termMonths,
            'processing_fee_rate' => $loanProduct->processing_fee_rate,
            'penalty_rate' => $loanProduct->penalty_rate,
            'grace_period_days' => $loanProduct->grace_period_days,
            'status' => LoanStatus::Pending,
            'applied_at' => now(),
            'notes' => $notes,
        ]);

        $this->recordEvent($loan, LoanEventType::Created, null, LoanStatus::Pending, $actor);

        return $loan;
    }

    /**
     * Move the loan to the given status, guarding the transition.
     */
    public function transition(Loan $loan, LoanStatus $status, ?User $actor = null, ?CarbonInterface $at = null): Loan
    {
        if (! $loan->status->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => __('A :from loan cannot be moved to :to.', [
                    'from' => $loan->status->label(),
                    'to' => $status->label(),
                ]),
            ]);
        }

        $fromStatus = $loan->status;

        $loan->status = $status;

        if ($column = $this->timestampColumnFor($status)) {
            $loan->{$column} = $at ?? now();
        }

        $loan->save();

        $this->recordEvent($loan, LoanEventType::fromStatus($status), $fromStatus, $status, $actor);

        return $loan;
    }

    /**
     * Approve the given loan and build its repayment schedule.
     */
    public function approve(Loan $loan, ?User $actor = null): Loan
    {
        $loan = $this->transition($loan, LoanStatus::Approved, $actor);

        $this->scheduleService->generate($loan, $loan->approved_at ?? now());

        return $loan;
    }

    /**
     * Reject the given loan.
     */
    public function reject(Loan $loan, ?User $actor = null): Loan
    {
        return $this->transition($loan, LoanStatus::Rejected, $actor);
    }

    /**
     * Disburse the loan, record the payout, rebuild the schedule around the
     * disbursed amount and date, and activate the loan.
     */
    public function disburse(
        Loan $loan,
        User $actor,
        float $amount,
        DisbursementMethod $method,
        ?string $reference = null,
        ?CarbonInterface $disbursedAt = null,
    ): Loan {
        $disbursedAt ??= now();

        Disbursement::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'disbursed_at' => $disbursedAt,
            'recorded_by' => $actor->id,
        ]);

        $this->scheduleService->generate($loan, $disbursedAt, $amount);

        $loan = $this->transition($loan, LoanStatus::Disbursed, $actor, $disbursedAt);

        return $this->transition($loan, LoanStatus::Active, $actor);
    }

    /**
     * Flag a loan as defaulted.
     */
    public function markDefaulted(Loan $loan, ?User $actor = null): Loan
    {
        return $this->transition($loan, LoanStatus::Defaulted, $actor);
    }

    /**
     * Write off a loan that is not expected to be repaid.
     */
    public function writeOff(Loan $loan, ?User $actor = null): Loan
    {
        return $this->transition($loan, LoanStatus::WrittenOff, $actor);
    }

    /**
     * Reschedule the outstanding principal of an active or defaulted loan onto
     * a fresh schedule, reactivating defaulted loans.
     */
    public function restructure(Loan $loan, User $actor, int $termMonths, ?CarbonInterface $startsOn = null): Loan
    {
        if (! in_array($loan->status, [LoanStatus::Active, LoanStatus::Defaulted], true)) {
            throw ValidationException::withMessages([
                'status' => __('Only active or defaulted loans can be restructured.'),
            ]);
        }

        $outstandingPrincipal = $this->outstandingPrincipal($loan);

        if ($outstandingPrincipal <= 0) {
            throw ValidationException::withMessages([
                'loan' => __('This loan has no outstanding principal to restructure.'),
            ]);
        }

        $fromStatus = $loan->status;

        $loan->term_months = $termMonths;
        $loan->status = LoanStatus::Active;
        $loan->save();

        $this->scheduleService->generate($loan, $startsOn ?? now(), $outstandingPrincipal);

        $this->recordEvent($loan, LoanEventType::Restructured, $fromStatus, $loan->status, $actor);

        return $loan;
    }

    /**
     * Sum the unpaid principal across the loan's installments.
     */
    private function outstandingPrincipal(Loan $loan): float
    {
        return round($loan->installments()
            ->with('allocations')
            ->get()
            ->sum(function (LoanInstallment $installment): float {
                $principalPaid = $installment->allocations->sum(
                    fn (LoanPaymentAllocation $allocation): float => (float) $allocation->principal_allocated,
                );

                return max(round((float) $installment->principal_due - $principalPaid, 2), 0.0);
            }), 2);
    }

    /**
     * Record an audit event against the loan.
     */
    private function recordEvent(Loan $loan, LoanEventType $action, ?LoanStatus $from, ?LoanStatus $to, ?User $actor): LoanEvent
    {
        return LoanEvent::create([
            'loan_id' => $loan->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }

    /**
     * Get the timestamp column to stamp for the given status, if any.
     */
    private function timestampColumnFor(LoanStatus $status): ?string
    {
        return match ($status) {
            LoanStatus::Approved => 'approved_at',
            LoanStatus::Rejected => 'rejected_at',
            LoanStatus::Disbursed => 'disbursed_at',
            LoanStatus::Closed => 'closed_at',
            default => null,
        };
    }
}
