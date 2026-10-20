<?php

namespace App\Models;

use App\Enums\DisbursementMethod;
use App\Models\Concerns\AuditsActivity;
use Database\Factories\DisbursementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $loan_id
 * @property string $amount
 * @property DisbursementMethod $method
 * @property string|null $reference
 * @property Carbon $disbursed_at
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $recordedBy
 */
#[Fillable([
    'loan_id',
    'amount',
    'method',
    'reference',
    'disbursed_at',
    'recorded_by',
])]
class Disbursement extends Model
{
    /** @use HasFactory<DisbursementFactory> */
    use AuditsActivity, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => DisbursementMethod::class,
            'disbursed_at' => 'datetime',
        ];
    }

    /**
     * Get the loan the disbursement belongs to.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user who recorded the disbursement.
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
