<?php

namespace App\Enums;

enum ApprovalType: string
{
    case LoanWriteOff = 'loan_write_off';
    case PaymentReversal = 'payment_reversal';

    /**
     * Get the human readable label for the request type.
     */
    public function label(): string
    {
        return match ($this) {
            self::LoanWriteOff => 'Loan write-off',
            self::PaymentReversal => 'Payment reversal',
        };
    }
}
