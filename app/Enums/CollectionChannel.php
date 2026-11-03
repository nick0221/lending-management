<?php

namespace App\Enums;

enum CollectionChannel: string
{
    case Call = 'call';
    case Sms = 'sms';
    case Email = 'email';
    case Visit = 'visit';
    case Other = 'other';

    /**
     * Get the human readable label for the channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Call => 'Phone call',
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::Visit => 'Field visit',
            self::Other => 'Other',
        };
    }
}
