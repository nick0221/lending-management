<?php

namespace App\Models;

use Database\Factories\LoanInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $loan_id
 * @property int $sequence
 * @property Carbon $due_date
 * @property string $principal_due
 * @property string $interest_due
 * @property string $fee_due
 * @property string $balance
 * @property string $penalty_accrued
 * @property int $penalty_accrued_periods
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'loan_id',
    'sequence',
    'due_date',
    'principal_due',
    'interest_due',
    'fee_due',
    'balance',
    'penalty_accrued',
    'penalty_accrued_periods',
])]
class LoanInstallment extends Model
{
    /** @use HasFactory<LoanInstallmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'principal_due' => 'decimal:2',
            'interest_due' => 'decimal:2',
            'fee_due' => 'decimal:2',
            'balance' => 'decimal:2',
            'penalty_accrued' => 'decimal:2',
            'penalty_accrued_periods' => 'integer',
        ];
    }

    /**
     * Get the loan the installment belongs to.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the payment allocations applied to the installment.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(LoanPaymentAllocation::class, 'installment_id');
    }

    /**
     * The amount due on the installment before penalties (principal + interest + fee).
     */
    public function dueAmount(): float
    {
        return round(
            (float) $this->principal_due + (float) $this->interest_due + (float) $this->fee_due,
            2,
        );
    }

    /**
     * The total scheduled amount still owed before penalties.
     */
    public function outstandingDue(): float
    {
        return max(round($this->dueAmount() - $this->allocatedTotal(), 2), 0.0);
    }

    /**
     * The accrued penalty still owed on the installment.
     */
    public function outstandingPenalty(): float
    {
        return max(round((float) $this->penalty_accrued - $this->allocatedPenalty(), 2), 0.0);
    }

    /**
     * The total amount still owed, including accrued penalties.
     */
    public function outstandingTotal(): float
    {
        return round($this->outstandingDue() + $this->outstandingPenalty(), 2);
    }

    /**
     * Determine whether the installment is due and not fully settled.
     */
    public function isOverdue(): bool
    {
        return $this->outstandingDue() > 0 && $this->due_date->copy()->startOfDay()->lessThan(today());
    }

    /**
     * The number of days the installment is past its due date.
     */
    public function daysPastDue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) abs($this->due_date->copy()->startOfDay()->diffInDays(today()));
    }

    /**
     * The total amount allocated to the installment across all components.
     */
    private function allocatedTotal(): float
    {
        return round(
            $this->allocatedPrincipal() + $this->allocatedInterest() + $this->allocatedFee(),
            2,
        );
    }

    private function allocatedPrincipal(): float
    {
        return round($this->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->principal_allocated), 2);
    }

    private function allocatedInterest(): float
    {
        return round($this->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->interest_allocated), 2);
    }

    private function allocatedFee(): float
    {
        return round($this->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->fee_allocated), 2);
    }

    private function allocatedPenalty(): float
    {
        return round($this->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->penalty_allocated), 2);
    }
}
