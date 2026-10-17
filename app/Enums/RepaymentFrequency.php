<?php

namespace App\Enums;

enum RepaymentFrequency: string
{
    case Monthly = 'monthly';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';

    /**
     * Get the human readable label for the repayment frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Weekly => 'Weekly',
            self::Biweekly => 'Biweekly',
        };
    }

    /**
     * Get the number of repayment periods in a year.
     */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Weekly => 52,
            self::Biweekly => 26,
        };
    }
}
