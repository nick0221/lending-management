<?php

namespace App\Models;

use App\Enums\KycStatus;
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
])]
class Borrower extends Model
{
    /** @use HasFactory<BorrowerFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    /**
     * Determine whether the borrower wants the given email notification.
     */
    public function wantsEmail(string $type): bool
    {
        if (blank($this->email)) {
            return false;
        }

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
