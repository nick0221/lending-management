<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use Illuminate\Notifications\Events\NotificationSent;

class MarkNotificationSent
{
    /**
     * Mark the matching delivery log as sent after a channel accepts it.
     */
    public function handle(NotificationSent $event): void
    {
        if (! method_exists($event->notification, 'logIdFor')) {
            return;
        }

        $logId = $event->notification->logIdFor($event->channel);

        if ($logId === null) {
            return;
        }

        NotificationLog::query()->find($logId)?->markSent();
    }
}
