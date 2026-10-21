<?php

namespace App\Notifications\Concerns;

use App\Models\NotificationLog;

/**
 * Carries the delivery log id through the queue so the notification events can
 * update the log once the mail channel has actually sent the message.
 */
trait TracksNotificationLog
{
    public ?int $notificationLogId = null;

    /**
     * Attach the delivery log to this notification.
     */
    public function forLog(NotificationLog $log): static
    {
        $this->notificationLogId = $log->id;

        return $this;
    }
}
