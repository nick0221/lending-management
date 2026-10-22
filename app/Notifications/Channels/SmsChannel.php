<?php

namespace App\Notifications\Channels;

use App\Services\Sms\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Delivers a notification through the configured SMS gateway.
 */
class SmsChannel
{
    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Send the notification when it exposes an SMS representation.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable->phone ?? null;

        if (blank($phone)) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if (blank($message)) {
            return;
        }

        $this->sms->send($phone, $message);
    }
}
