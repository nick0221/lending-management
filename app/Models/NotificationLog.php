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
 * @property string $status
 * @property string|null $error
 * @property int|null $borrower_id
 * @property int|null $loan_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'type', 'channel', 'status', 'error', 'borrower_id', 'loan_id', 'sent_at'])]
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
     * The notification has been handed to the queue but not delivered yet.
     */
    public const STATUS_QUEUED = 'queued';

    /**
     * The notification was delivered to the mail channel.
     */
    public const STATUS_SENT = 'sent';

    /**
     * The mail channel threw an error while delivering the notification.
     */
    public const STATUS_FAILED = 'failed';

    /**
     * The notification was deliberately not delivered.
     */
    public const STATUS_SKIPPED = 'skipped';

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
     * Default manually created logs with a send time to the sent status.
     */
    protected static function booted(): void
    {
        static::creating(function (NotificationLog $log): void {
            if (blank($log->status)) {
                $log->status = $log->sent_at !== null
                    ? self::STATUS_SENT
                    : self::STATUS_QUEUED;
            }
        });
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
     * Get the human-readable label for a delivery status.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_QUEUED => __('Queued'),
            self::STATUS_SENT => __('Sent'),
            self::STATUS_FAILED => __('Failed'),
            self::STATUS_SKIPPED => __('Skipped'),
            default => Str::headline($status),
        };
    }

    /**
     * Claim the given notification key, returning the log when it is new so
     * the caller can track delivery. Returns null when the key already exists.
     */
    public static function reserve(
        string $key,
        string $type,
        Borrower $borrower,
        ?Loan $loan = null,
        string $channel = 'mail',
    ): ?self {
        $log = static::query()->firstOrCreate(
            ['key' => $key],
            [
                'type' => $type,
                'channel' => $channel,
                'borrower_id' => $borrower->id,
                'loan_id' => $loan?->id,
                'status' => self::STATUS_QUEUED,
                'sent_at' => null,
            ],
        );

        return $log->wasRecentlyCreated ? $log : null;
    }

    /**
     * Mark the notification as handed to the queue again.
     */
    public function markQueued(): void
    {
        $this->forceFill([
            'status' => self::STATUS_QUEUED,
            'error' => null,
            'sent_at' => null,
        ])->save();
    }

    /**
     * Mark the notification as delivered.
     */
    public function markSent(): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'error' => null,
            'sent_at' => now(),
        ])->save();
    }

    /**
     * Record a delivery failure and keep the reason for the delivery log.
     */
    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error' => Str::limit($error, 1000),
            'sent_at' => null,
        ])->save();
    }

    /**
     * Record why the notification was deliberately not delivered.
     */
    public function markSkipped(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_SKIPPED,
            'error' => Str::limit($reason, 1000),
            'sent_at' => null,
        ])->save();
    }
}
