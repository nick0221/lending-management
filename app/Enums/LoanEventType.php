<?php

namespace App\Enums;

enum LoanEventType: string
{
    case Created = 'created';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Disbursed = 'disbursed';
    case Activated = 'activated';
    case Closed = 'closed';
    case Defaulted = 'defaulted';
    case WrittenOff = 'written_off';
    case PaymentRecorded = 'payment_recorded';
    case PaymentReversed = 'payment_reversed';
    case PenaltyAccrued = 'penalty_accrued';
    case Restructured = 'restructured';

    /**
     * Get the human readable label for the event.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Application created',
            self::Approved => 'Loan approved',
            self::Rejected => 'Loan rejected',
            self::Disbursed => 'Funds disbursed',
            self::Activated => 'Loan activated',
            self::Closed => 'Loan closed',
            self::Defaulted => 'Loan defaulted',
            self::WrittenOff => 'Loan written off',
            self::PaymentRecorded => 'Payment recorded',
            self::PaymentReversed => 'Payment reversed',
            self::PenaltyAccrued => 'Penalty accrued',
            self::Restructured => 'Loan restructured',
        };
    }

    /**
     * Map the status a loan has entered to the event that recorded it.
     */
    public static function fromStatus(LoanStatus $status): self
    {
        return match ($status) {
            LoanStatus::Draft, LoanStatus::Pending => self::Created,
            LoanStatus::Approved => self::Approved,
            LoanStatus::Rejected => self::Rejected,
            LoanStatus::Disbursed => self::Disbursed,
            LoanStatus::Active => self::Activated,
            LoanStatus::Closed => self::Closed,
            LoanStatus::Defaulted => self::Defaulted,
            LoanStatus::WrittenOff => self::WrittenOff,
        };
    }
}
