<?php

namespace App\Models;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use Database\Factories\LoanProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property InterestMethod $interest_method
 * @property string $interest_rate
 * @property RepaymentFrequency $repayment_frequency
 * @property string $min_amount
 * @property string $max_amount
 * @property int $min_term_months
 * @property int $max_term_months
 * @property string $processing_fee_rate
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'interest_method', 'interest_rate', 'repayment_frequency', 'min_amount', 'max_amount', 'min_term_months', 'max_term_months', 'processing_fee_rate', 'is_active'])]
class LoanProduct extends Model
{
    /** @use HasFactory<LoanProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => RepaymentFrequency::class,
            'interest_rate' => 'decimal:4',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'processing_fee_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Limit the query to active loan products.
     *
     * @param  Builder<LoanProduct>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
