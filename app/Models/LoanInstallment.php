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
}
