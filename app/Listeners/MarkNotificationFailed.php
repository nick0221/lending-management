<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use Illuminate\Notifications\Events\NotificationFailed;
use Throwable;

class MarkNotificationFailed
{
    /**
     * Record the exception that stopped the mail channel from delivering.
     */
    public function handle(NotificationFailed $event): void
    {
        $logId = $event->notification->notificationLogId ?? null;

        if ($logId === null) {
            return;
        }

        $exception = $event->data['exception'] ?? null;
        $message = $exception instanceof Throwable
            ? $exception->getMessage()
            : __('The notification could not be delivered.');

        NotificationLog::query()->find($logId)?->markFailed($message);
    }
}
