<?php

namespace App\Services;

use App\Enums\InterestMethod;
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
     * Preview a schedule without persisting a loan, returning the first few
     * installments plus totals for the whole term.
     *
     * @return array{
     *     installments: array<int, array{sequence: int, due_date: string, principal_due: float, interest_due: float, fee_due: float, balance: float, total: float}>,
     *     totals: array{principal: float, interest: float, fee: float, total: float},
     *     periods: int
     * }
     */
    public function preview(
        float $principal,
        float $annualRatePercent,
        InterestMethod $interestMethod,
        int $termMonths,
        RepaymentFrequency $repaymentFrequency,
        float $processingFeeRatePercent,
        CarbonInterface $startsOn,
        int $limit = 3,
    ): array {
        $rows = $this->calculator->calculate(
            principal: $principal,
            annualRatePercent: $annualRatePercent,
            interestMethod: $interestMethod,
            termMonths: $termMonths,
            repaymentFrequency: $repaymentFrequency,
            processingFeeRatePercent: $processingFeeRatePercent,
        );

        $installments = collect($rows)
            ->take($limit)
            ->map(fn (array $row): array => [
                ...$row,
                'due_date' => $this->dueDate($startsOn, $repaymentFrequency, $row['sequence'])->toDateString(),
                'total' => round($row['principal_due'] + $row['interest_due'] + $row['fee_due'], 2),
            ])
            ->values()
            ->all();

        $principalTotal = round(array_sum(array_column($rows, 'principal_due')), 2);
        $interestTotal = round(array_sum(array_column($rows, 'interest_due')), 2);
        $feeTotal = round(array_sum(array_column($rows, 'fee_due')), 2);

        return [
            'installments' => $installments,
            'totals' => [
                'principal' => $principalTotal,
                'interest' => $interestTotal,
                'fee' => $feeTotal,
                'total' => round($principalTotal + $interestTotal + $feeTotal, 2),
            ],
            'periods' => count($rows),
        ];
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
