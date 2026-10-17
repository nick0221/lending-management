<?php

namespace App\Services;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;

class AmortizationCalculator
{
    /**
     * Break a loan into per-period amounts that reconcile exactly to the
     * principal, interest and processing fee. Due dates belong to the caller.
     *
     * @return array<int, array{sequence: int, principal_due: float, interest_due: float, fee_due: float, balance: float}>
     */
    public function calculate(
        float $principal,
        float $annualRatePercent,
        InterestMethod $interestMethod,
        int $termMonths,
        RepaymentFrequency $repaymentFrequency,
        float $processingFeeRatePercent = 0,
    ): array {
        $periods = $this->periodCount($termMonths, $repaymentFrequency);
        $feeTotal = round($principal * $processingFeeRatePercent / 100, 2);

        if ($interestMethod === InterestMethod::Flat) {
            return $this->flat($principal, $annualRatePercent, $termMonths, $periods, $feeTotal);
        }

        return $this->decliningBalance($principal, $annualRatePercent, $repaymentFrequency, $periods, $feeTotal);
    }

    /**
     * Split a principal or total into cents-exact parts, putting the rounding
     * remainder on the final periods so the parts sum to the total.
     *
     * @return array<int, float>
     */
    private function distribute(float $total, int $periods): array
    {
        $cents = (int) round($total * 100);
        $base = intdiv($cents, $periods);
        $remainder = $cents - ($base * $periods);

        $parts = [];

        for ($index = 0; $index < $periods; $index++) {
            $extra = $index >= $periods - $remainder ? 1 : 0;
            $parts[] = (float) (($base + $extra) / 100);
        }

        return $parts;
    }

    /**
     * The number of payments the term produces at the given frequency.
     */
    private function periodCount(int $termMonths, RepaymentFrequency $repaymentFrequency): int
    {
        return match ($repaymentFrequency) {
            RepaymentFrequency::Monthly => max(1, $termMonths),
            RepaymentFrequency::Weekly => max(1, (int) round($termMonths * 52 / 12)),
            RepaymentFrequency::Biweekly => max(1, (int) round($termMonths * 52 / 12 / 2)),
        };
    }

    /**
     * Payments per year for the given frequency, used to scale the annual rate.
     */
    private function periodsPerYear(RepaymentFrequency $repaymentFrequency): float
    {
        return match ($repaymentFrequency) {
            RepaymentFrequency::Monthly => 12,
            RepaymentFrequency::Weekly => 52,
            RepaymentFrequency::Biweekly => 26,
        };
    }

    /**
     * Flat interest: the same interest is charged on the original principal for
     * every period, so principal and interest are both level.
     *
     * @return array<int, array{sequence: int, principal_due: float, interest_due: float, fee_due: float, balance: float}>
     */
    private function flat(float $principal, float $annualRatePercent, int $termMonths, int $periods, float $feeTotal): array
    {
        $totalInterest = round($principal * ($annualRatePercent / 100) * ($termMonths / 12), 2);

        $principalParts = $this->distribute($principal, $periods);
        $interestParts = $this->distribute($totalInterest, $periods);
        $feeParts = $this->distribute($feeTotal, $periods);

        $balance = round($principal, 2);
        $rows = [];

        for ($sequence = 1; $sequence <= $periods; $sequence++) {
            $balance = round($balance - $principalParts[$sequence - 1], 2);

            $rows[] = [
                'sequence' => $sequence,
                'principal_due' => $principalParts[$sequence - 1],
                'interest_due' => $interestParts[$sequence - 1],
                'fee_due' => $feeParts[$sequence - 1],
                'balance' => $balance,
            ];
        }

        return $rows;
    }

    /**
     * Declining balance: interest is charged on the outstanding balance, so the
     * interest portion falls while the principal portion rises. The final
     * period clears whatever principal remains so the parts reconcile exactly.
     *
     * @return array<int, array{sequence: int, principal_due: float, interest_due: float, fee_due: float, balance: float}>
     */
    private function decliningBalance(float $principal, float $annualRatePercent, RepaymentFrequency $repaymentFrequency, int $periods, float $feeTotal): array
    {
        $periodicRate = ($annualRatePercent / 100) / $this->periodsPerYear($repaymentFrequency);

        $payment = $periodicRate > 0
            ? $principal * $periodicRate / (1 - (1 + $periodicRate) ** -$periods)
            : $principal / $periods;

        $feeParts = $this->distribute($feeTotal, $periods);

        $balance = round($principal, 2);
        $rows = [];

        for ($sequence = 1; $sequence <= $periods; $sequence++) {
            $interestDue = round($balance * $periodicRate, 2);

            $principalDue = $sequence === $periods
                ? $balance
                : min(max(round($payment - $interestDue, 2), 0), $balance);

            $balance = round($balance - $principalDue, 2);

            $rows[] = [
                'sequence' => $sequence,
                'principal_due' => round($principalDue, 2),
                'interest_due' => $interestDue,
                'fee_due' => $feeParts[$sequence - 1],
                'balance' => $balance,
            ];
        }

        return $rows;
    }
}
