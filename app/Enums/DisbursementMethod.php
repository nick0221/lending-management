<?php

namespace App\Enums;

enum DisbursementMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case MobileMoney = 'mobile_money';
    case Other = 'other';

    /**
     * Get the human readable label for the method.
     */
    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank Transfer',
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
            self::MobileMoney => 'Mobile Money',
            self::Other => 'Other',
        };
    }
}
