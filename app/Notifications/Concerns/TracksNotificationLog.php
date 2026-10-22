<?php

namespace App\Notifications\Concerns;

use App\Models\Borrower;
use App\Models\NotificationLog;

/**
 * Carries the delivery log ids and selected channels through the queue so the
 * notification events can update the matching log after each channel sends.
 */
trait TracksNotificationLog
{
    public ?int $notificationLogId = null;

    /**
     * Delivery log id keyed by channel.
     *
     * @var array<string, int>
     */
    public array $notificationLogIds = [];

    /**
     * Channels chosen for this send, or null to fall back to preferences.
     *
     * @var array<int, string>|null
     */
    public ?array $channels = null;

    /**
     * Attach one delivery log to this notification.
     */
    public function forLog(NotificationLog $log): static
    {
        $this->notificationLogIds[$log->channel] = $log->id;

        if ($log->channel === NotificationLog::CHANNEL_MAIL) {
            $this->notificationLogId = $log->id;
        }

        return $this;
    }

    /**
     * @param  iterable<int, NotificationLog>  $logs
     */
    public function forLogs(iterable $logs): static
    {
        foreach ($logs as $log) {
            $this->forLog($log);
        }

        return $this;
    }

    /**
     * @param  array<int, string>  $channels
     */
    public function forChannels(array $channels): static
    {
        $this->channels = array_values($channels);

        return $this;
    }

    /**
     * Find the delivery log for a Laravel notification event channel.
     */
    public function logIdFor(string $channel): ?int
    {
        return $this->notificationLogIds[$channel]
            ?? ($channel === NotificationLog::CHANNEL_MAIL
                ? $this->notificationLogId
                : null);
    }

    /**
     * Get the explicitly selected channels or the borrower's preferences.
     *
     * @return array<int, string>
     */
    protected function channelsFor(object $notifiable, string $type): array
    {
        if ($this->channels !== null) {
            return $this->channels;
        }

        return $notifiable instanceof Borrower
            ? $notifiable->notificationChannels($type)
            : [NotificationLog::CHANNEL_MAIL];
    }
}
