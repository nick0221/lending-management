<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $key
 * @property string $type
 * @property string $channel
 * @property int|null $borrower_id
 * @property int|null $loan_id
 * @property Carbon $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'type', 'channel', 'borrower_id', 'loan_id', 'sent_at'])]
class NotificationLog extends Model
{
    /**
     * Type of a reminder sent before an installment is due.
     */
    public const TYPE_DUE_SOON = 'due_soon';

    /**
     * Type of a notice sent once an installment is past due.
     */
    public const TYPE_OVERDUE = 'overdue';

    /**
     * Type of a receipt sent when a payment is recorded.
     */
    public const TYPE_RECEIPT = 'receipt';

    /**
     * Type of a statement emailed on request.
     */
    public const TYPE_STATEMENT = 'statement';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the borrower the notification was sent to.
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    /**
     * Get the loan the notification belongs to.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the human-readable label for a notification type.
     */
    public static function label(string $type): string
    {
        return match ($type) {
            self::TYPE_DUE_SOON => __('Due soon'),
            self::TYPE_OVERDUE => __('Overdue'),
            self::TYPE_RECEIPT => __('Payment receipt'),
            self::TYPE_STATEMENT => __('Statement'),
            default => Str::headline($type),
        };
    }

    /**
     * Claim the given notification key, returning false when it was already
     * logged so a command can never send the same notice twice.
     */
    public static function reserve(
        string $key,
        string $type,
        Borrower $borrower,
        ?Loan $loan = null,
        string $channel = 'mail',
    ): bool {
        $log = static::query()->firstOrCreate(
            ['key' => $key],
            [
                'type' => $type,
                'channel' => $channel,
                'borrower_id' => $borrower->id,
                'loan_id' => $loan?->id,
                'sent_at' => now(),
            ],
        );

        return $log->wasRecentlyCreated;
    }
}
