<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the audit trail for authentication events and model changes.
 */
class AuditLogger
{
    /**
     * Attributes that are never written to the audit trail.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_ATTRIBUTES = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Record an activity for the currently authenticated user.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $event,
        string $description,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
    ): ActivityLog {
        $request = request();

        return ActivityLog::create([
            'user_id' => $user?->id ?? auth()->id(),
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() !== null
                ? Str::limit($request->userAgent(), 255, '')
                : null,
        ]);
    }

    /**
     * Record a model event, diffing updates and skipping sensitive values.
     */
    public function recordModel(string $event, Model $model): ?ActivityLog
    {
        $excluded = $this->excludedAttributes($model);

        if ($event === ActivityLog::EVENT_UPDATED) {
            $changes = array_diff_key($model->getChanges(), array_flip($excluded));

            if ($changes === []) {
                return null;
            }

            return $this->log(
                $event,
                $this->describe($model, $event),
                $model,
                $this->clean(array_intersect_key($model->getOriginal(), $changes), $excluded),
                $this->clean($changes, $excluded),
            );
        }

        $values = $this->clean($model->attributesToArray(), $excluded);

        return $this->log(
            $event,
            $this->describe($model, $event),
            $model,
            $event === ActivityLog::EVENT_DELETED ? $values : null,
            $event === ActivityLog::EVENT_CREATED ? $values : null,
        );
    }

    /**
     * Get the attributes excluded for the given model.
     *
     * @return array<int, string>
     */
    private function excludedAttributes(Model $model): array
    {
        $modelExcluded = method_exists($model, 'auditExcludedAttributes')
            ? $model->auditExcludedAttributes()
            : [];

        return array_values(array_unique([
            ...self::SENSITIVE_ATTRIBUTES,
            ...$modelExcluded,
            'created_at',
            'updated_at',
        ]));
    }

    /**
     * Remove excluded attributes from a value set.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $excluded
     * @return array<string, mixed>
     */
    private function clean(array $values, array $excluded): array
    {
        return array_diff_key($values, array_flip($excluded));
    }

    /**
     * Build a human readable summary of the model event.
     */
    private function describe(Model $model, string $event): string
    {
        return sprintf(
            '%s #%s %s',
            Str::headline(class_basename($model)),
            $model->getKey(),
            $event,
        );
    }
}
