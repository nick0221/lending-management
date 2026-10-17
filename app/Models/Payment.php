<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $loan_id
 * @property int|null $installment_id
 * @property string $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property string|null $notes
 * @property Carbon $payment_date
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'loan_id',
    'installment_id',
    'amount',
    'method',
    'reference',
    'notes',
    'payment_date',
    'recorded_by',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'payment_date' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'installment_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoanPaymentAllocation::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
