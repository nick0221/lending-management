<?php

namespace App\Services;

use App\Enums\RepaymentFrequency;
use App\Models\Loan;
use App\Models\LoanInstallment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ScheduleService
{
    public function __construct(private AmortizationCalculator $calculator) {}

    /**
     * Build the installment schedule for a loan, replacing any schedule that
     * is already there so it can be regenerated on approval or disbursement.
     *
     * @return Collection<int, LoanInstallment>
     */
    public function generate(Loan $loan, CarbonInterface $startsOn, ?float $principal = null): Collection
    {
        $loan->installments()->delete();

        $principal ??= (float) $loan->principal;

        $rows = $this->calculator->calculate(
            principal: $principal,
            annualRatePercent: (float) $loan->interest_rate,
            interestMethod: $loan->interest_method,
            termMonths: $loan->term_months,
            repaymentFrequency: $loan->repayment_frequency,
            processingFeeRatePercent: (float) $loan->processing_fee_rate,
        );

        return collect($rows)->map(fn (array $row): LoanInstallment => LoanInstallment::create([
            'loan_id' => $loan->id,
            'sequence' => $row['sequence'],
            'due_date' => $this->dueDate($startsOn, $loan->repayment_frequency, $row['sequence']),
            'principal_due' => $row['principal_due'],
            'interest_due' => $row['interest_due'],
            'fee_due' => $row['fee_due'],
            'balance' => $row['balance'],
        ]));
    }

    /**
     * Advance the start date by the number of periods the sequence represents.
     */
    private function dueDate(CarbonInterface $startsOn, RepaymentFrequency $repaymentFrequency, int $sequence): CarbonInterface
    {
        return match ($repaymentFrequency) {
            RepaymentFrequency::Monthly => $startsOn->copy()->addMonths($sequence),
            RepaymentFrequency::Biweekly => $startsOn->copy()->addWeeks($sequence * 2),
            RepaymentFrequency::Weekly => $startsOn->copy()->addWeeks($sequence),
        };
    }
}
