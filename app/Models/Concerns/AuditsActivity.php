<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create, update and delete events on a model in the audit trail.
 */
trait AuditsActivity
{
    /**
     * Register the model event listeners.
     */
    protected static function bootAuditsActivity(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->recordModel(ActivityLog::EVENT_CREATED, $model);
        });

        static::updated(function (Model $model): void {
            app(AuditLogger::class)->recordModel(ActivityLog::EVENT_UPDATED, $model);
        });

        static::deleted(function (Model $model): void {
            app(AuditLogger::class)->recordModel(ActivityLog::EVENT_DELETED, $model);
        });
    }

    /**
     * Get the attributes excluded from the audit trail for this model.
     *
     * @return array<int, string>
     */
    public function auditExcludedAttributes(): array
    {
        return [];
    }
}
