<?php

namespace App\Models;

use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use Database\Factories\LoanEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $loan_id
 * @property int|null $user_id
 * @property LoanEventType $action
 * @property LoanStatus|null $from_status
 * @property LoanStatus|null $to_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'loan_id',
    'user_id',
    'action',
    'from_status',
    'to_status',
])]
class LoanEvent extends Model
{
    /** @use HasFactory<LoanEventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => LoanEventType::class,
            'from_status' => LoanStatus::class,
            'to_status' => LoanStatus::class,
        ];
    }

    /**
     * Get the loan the event belongs to.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user who performed the action, if a user was recorded.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
