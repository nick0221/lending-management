<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use Illuminate\Notifications\Events\NotificationSkipped;

class MarkNotificationSkipped
{
    /**
     * Record that the notification was deliberately not delivered.
     */
    public function handle(NotificationSkipped $event): void
    {
        if (! method_exists($event->notification, 'logIdFor')) {
            return;
        }

        $logId = $event->notification->logIdFor($event->channel);

        if ($logId === null) {
            return;
        }

        NotificationLog::query()->find($logId)?->markSkipped(
            __('The notification channel skipped this message.'),
        );
    }
}
