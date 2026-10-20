<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $event
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'event',
    'description',
    'subject_type',
    'subject_id',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
])]
class ActivityLog extends Model
{
    /**
     * Event name for a model that was created.
     */
    public const EVENT_CREATED = 'created';

    /**
     * Event name for a model that was updated.
     */
    public const EVENT_UPDATED = 'updated';

    /**
     * Event name for a model that was deleted.
     */
    public const EVENT_DELETED = 'deleted';

    /**
     * Event name for a successful sign in.
     */
    public const EVENT_LOGIN = 'login';

    /**
     * Event name for a sign out.
     */
    public const EVENT_LOGOUT = 'logout';

    /**
     * Event name for a failed sign in attempt.
     */
    public const EVENT_LOGIN_FAILED = 'login_failed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Get the user that performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the model the activity was recorded against.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
