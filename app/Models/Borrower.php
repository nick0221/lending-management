<?php

namespace App\Models;

use App\Enums\KycStatus;
use Database\Factories\BorrowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'national_id', 'phone', 'email', 'date_of_birth', 'address', 'kyc_status', 'notes'])]
class Borrower extends Model
{
    /** @use HasFactory<BorrowerFactory> */
    use HasFactory;

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
        ];
    }

    /**
     * Get the user account linked to the borrower.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
