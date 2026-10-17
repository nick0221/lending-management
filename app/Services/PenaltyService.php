<?php

namespace App\Services;

use App\Enums\LoanEventType;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\LoanInstallment;
use App\Models\User;
use Carbon\CarbonInterface;

class PenaltyService
{
    /**
     * Days in an accrual period. Penalties carry a monthly percentage that is
     * applied once for every whole 30-day period an installment is overdue.
     */
    private const DAYS_PER_PERIOD = 30;

    /**
     * Accrue penalties for every overdue installment of the loan.
     *
     * @return array{amount: float, installments: int}
     */
    public function accrueForLoan(Loan $loan, ?CarbonInterface $asOf = null, ?User $actor = null): array
    {
        if ((float) $loan->penalty_rate <= 0) {
            return ['amount' => 0.0, 'installments' => 0];
        }

        $asOf ??= now();
        $total = 0.0;
        $count = 0;

        $installments = $loan->installments()->with('allocations')->orderBy('sequence')->get();

        foreach ($installments as $installment) {
            $accrued = $this->accrueForInstallment($loan, $installment, $asOf);

            if ($accrued > 0) {
                $total = round($total + $accrued, 2);
                $count++;
            }
        }

        if ($total > 0) {
            LoanEvent::create([
                'loan_id' => $loan->id,
                'user_id' => $actor?->id,
                'action' => LoanEventType::PenaltyAccrued,
                'from_status' => $loan->status,
                'to_status' => $loan->status,
            ]);
        }

        return ['amount' => $total, 'installments' => $count];
    }

    /**
     * Accrue the penalty owing on a single installment, respecting the loan's
     * grace period. Returns the amount accrued.
     */
    public function accrueForInstallment(Loan $loan, LoanInstallment $installment, ?CarbonInterface $asOf = null): float
    {
        $rate = (float) $loan->penalty_rate;

        if ($rate <= 0) {
            return 0.0;
        }

        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $graceEnds = $installment->due_date->copy()->startOfDay()->addDays((int) $loan->grace_period_days);

        if ($asOf->lessThan($graceEnds)) {
            return 0.0;
        }

        $periodsDue = intdiv((int) abs($graceEnds->diffInDays($asOf)), self::DAYS_PER_PERIOD);
        $newPeriods = $periodsDue - (int) $installment->penalty_accrued_periods;

        if ($newPeriods <= 0) {
            return 0.0;
        }

        $base = $installment->outstandingDue();

        if ($base <= 0) {
            return 0.0;
        }

        $accrued = round($base * ($rate / 100) * $newPeriods, 2);

        $installment->forceFill([
            'penalty_accrued' => round((float) $installment->penalty_accrued + $accrued, 2),
            'penalty_accrued_periods' => $periodsDue,
        ])->save();

        return $accrued;
    }
}
