<?php

namespace App\Enums;

enum CollectionOutcome: string
{
    case Promised = 'promised';
    case Contacted = 'contacted';
    case NoAnswer = 'no_answer';
    case Refused = 'refused';
    case Paid = 'paid';
    case Other = 'other';

    /**
     * Get the human readable label for the outcome.
     */
    public function label(): string
    {
        return match ($this) {
            self::Promised => 'Promised to pay',
            self::Contacted => 'Contacted',
            self::NoAnswer => 'No answer',
            self::Refused => 'Refused to pay',
            self::Paid => 'Paid',
            self::Other => 'Other',
        };
    }

    /**
     * Determine whether the outcome records a promise to pay.
     */
    public function isPromise(): bool
    {
        return $this === self::Promised;
    }
}
