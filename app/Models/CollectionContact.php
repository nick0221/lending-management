<?php

namespace App\Models;

use App\Enums\CollectionChannel;
use App\Enums\CollectionOutcome;
use App\Models\Concerns\AuditsActivity;
use Carbon\CarbonImmutable;
use Database\Factories\CollectionContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $loan_id
 * @property int|null $user_id
 * @property CollectionChannel $channel
 * @property CollectionOutcome $outcome
 * @property CarbonImmutable $contacted_at
 * @property string|null $notes
 * @property string|null $promised_amount
 * @property CarbonImmutable|null $promised_at
 * @property CarbonImmutable|null $follow_up_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'loan_id',
    'user_id',
    'channel',
    'outcome',
    'contacted_at',
    'notes',
    'promised_amount',
    'promised_at',
    'follow_up_at',
])]
class CollectionContact extends Model
{
    /** @use HasFactory<CollectionContactFactory> */
    use AuditsActivity, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CollectionChannel::class,
            'outcome' => CollectionOutcome::class,
            'contacted_at' => 'datetime',
            'promised_amount' => 'decimal:2',
            'promised_at' => 'date',
            'follow_up_at' => 'date',
        ];
    }

    /**
     * Get the loan the contact was logged against.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the staff member who logged the contact.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to logged promises to pay.
     */
    public function scopePromises(Builder $query): Builder
    {
        return $query->where('outcome', CollectionOutcome::Promised);
    }

    /**
     * Determine whether the contact records a promise that is still ahead.
     */
    public function isOpenPromise(): bool
    {
        return $this->outcome->isPromise()
            && $this->promised_at !== null
            && $this->promised_at->gte(today());
    }
}
