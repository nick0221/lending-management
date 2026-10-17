<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Disbursed = 'disbursed';
    case Active = 'active';
    case Closed = 'closed';
    case Defaulted = 'defaulted';
    case WrittenOff = 'written_off';

    /**
     * Get the human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Disbursed => 'Disbursed',
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Defaulted => 'Defaulted',
            self::WrittenOff => 'Written Off',
        };
    }

    /**
     * Get the statuses this status may transition to.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending],
            self::Pending => [self::Approved, self::Rejected],
            self::Approved => [self::Disbursed],
            self::Disbursed => [self::Active],
            self::Active => [self::Closed, self::Defaulted, self::WrittenOff],
            self::Defaulted => [self::Active, self::WrittenOff, self::Closed],
            self::Rejected, self::Closed, self::WrittenOff => [],
        };
    }

    /**
     * Determine whether this status may transition to the given status.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Determine whether this status is terminal.
     */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Determine whether the loan has already paid out funds.
     */
    public function hasBeenDisbursed(): bool
    {
        return match ($this) {
            self::Disbursed, self::Active, self::Closed, self::Defaulted, self::WrittenOff => true,
            self::Draft, self::Pending, self::Approved, self::Rejected => false,
        };
    }
}
