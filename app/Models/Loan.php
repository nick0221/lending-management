<?php

namespace App\Models;

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $borrower_id
 * @property int $loan_product_id
 * @property string $principal
 * @property string $interest_rate
 * @property InterestMethod $interest_method
 * @property RepaymentFrequency $repayment_frequency
 * @property int $term_months
 * @property string $processing_fee_rate
 * @property LoanStatus $status
 * @property Carbon|null $applied_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $disbursed_at
 * @property Carbon|null $closed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'borrower_id',
    'loan_product_id',
    'principal',
    'interest_rate',
    'interest_method',
    'repayment_frequency',
    'term_months',
    'processing_fee_rate',
    'status',
    'applied_at',
    'approved_at',
    'rejected_at',
    'disbursed_at',
    'closed_at',
    'notes',
])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => RepaymentFrequency::class,
            'processing_fee_rate' => 'decimal:4',
            'status' => LoanStatus::class,
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get the borrower the loan belongs to.
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    /**
     * Get the loan product the loan was originated from.
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * Get the repayment schedule for the loan.
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    /**
     * Get the disbursements recorded against the loan.
     */
    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class);
    }

    /**
     * Get the audit trail of events recorded against the loan.
     */
    public function events(): HasMany
    {
        return $this->hasMany(LoanEvent::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoanPaymentAllocation::class);
    }

    /**
     * Scope the query to loans with a given status.
     */
    public function scopeOfStatus(Builder $query, LoanStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
