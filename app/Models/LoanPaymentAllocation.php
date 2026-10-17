<?php

namespace App\Models;

use Database\Factories\LoanPaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_id
 * @property int $loan_id
 * @property int $installment_id
 * @property string $principal_allocated
 * @property string $interest_allocated
 * @property string $fee_allocated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'payment_id',
    'loan_id',
    'installment_id',
    'principal_allocated',
    'interest_allocated',
    'fee_allocated',
])]
class LoanPaymentAllocation extends Model
{
    /** @use HasFactory<LoanPaymentAllocationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal_allocated' => 'decimal:2',
            'interest_allocated' => 'decimal:2',
            'fee_allocated' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'installment_id');
    }
}
