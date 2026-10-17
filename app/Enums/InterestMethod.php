<?php

namespace App\Enums;

enum InterestMethod: string
{
    case Flat = 'flat';
    case DecliningBalance = 'declining_balance';

    /**
     * Get the human readable label for the interest method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat',
            self::DecliningBalance => 'Declining Balance',
        };
    }
}
