<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use Illuminate\Notifications\Events\NotificationSent;

class MarkNotificationSent
{
    /**
     * Mark the delivery log as sent after the mail channel accepts it.
     */
    public function handle(NotificationSent $event): void
    {
        $logId = $event->notification->notificationLogId ?? null;

        if ($logId === null) {
            return;
        }

        NotificationLog::query()->find($logId)?->markSent();
    }
}
