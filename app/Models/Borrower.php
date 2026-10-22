<?php

namespace App\Models;

use App\Enums\KycStatus;
use App\Models\Concerns\AuditsActivity;
use Database\Factories\BorrowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $national_id
 * @property string $phone
 * @property string|null $email
 * @property Carbon|null $date_of_birth
 * @property string|null $address
 * @property KycStatus $kyc_status
 * @property string|null $notes
 * @property bool $notify_due_soon
 * @property bool $notify_overdue
 * @property bool $notify_receipts
 * @property bool $notify_statements
 * @property bool $notify_sms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'name',
    'national_id',
    'phone',
    'email',
    'date_of_birth',
    'address',
    'kyc_status',
    'notes',
    'notify_due_soon',
    'notify_overdue',
    'notify_receipts',
    'notify_statements',
    'notify_sms',
])]
class Borrower extends Model
{
    /** @use HasFactory<BorrowerFactory> */
    use AuditsActivity, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'kyc_status' => KycStatus::class,
            'notify_due_soon' => 'boolean',
            'notify_overdue' => 'boolean',
            'notify_receipts' => 'boolean',
            'notify_statements' => 'boolean',
            'notify_sms' => 'boolean',
        ];
    }

    /**
     * Determine whether the borrower wants the given email notification.
     */
    public function wantsEmail(string $type): bool
    {
        return filled($this->email) && $this->wantsType($type);
    }

    /**
     * Determine whether the borrower wants the given SMS notification.
     *
     * Statements stay on email because a text message cannot carry the
     * repayment schedule.
     */
    public function wantsSms(string $type): bool
    {
        return $this->notify_sms
            && filled($this->phone)
            && in_array($type, [
                NotificationLog::TYPE_DUE_SOON,
                NotificationLog::TYPE_OVERDUE,
                NotificationLog::TYPE_RECEIPT,
            ], true)
            && $this->wantsType($type);
    }

    /**
     * Get the delivery channels enabled for a notification type.
     *
     * @return array<int, string>
     */
    public function notificationChannels(string $type): array
    {
        return array_values(array_filter([
            $this->wantsEmail($type) ? NotificationLog::CHANNEL_MAIL : null,
            $this->wantsSms($type) ? NotificationLog::CHANNEL_SMS : null,
        ]));
    }

    /**
     * Determine whether the borrower wants one specific delivery channel.
     */
    public function wantsChannel(string $type, string $channel): bool
    {
        return match ($channel) {
            NotificationLog::CHANNEL_MAIL => $this->wantsEmail($type),
            NotificationLog::CHANNEL_SMS => $this->wantsSms($type),
            default => false,
        };
    }

    /**
     * Determine whether the event-level preference is enabled.
     */
    private function wantsType(string $type): bool
    {
        return match ($type) {
            NotificationLog::TYPE_DUE_SOON => $this->notify_due_soon,
            NotificationLog::TYPE_OVERDUE => $this->notify_overdue,
            NotificationLog::TYPE_RECEIPT => $this->notify_receipts,
            NotificationLog::TYPE_STATEMENT => $this->notify_statements,
            default => false,
        };
    }

    /**
     * Get the user account linked to the borrower.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the loans belonging to the borrower.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
