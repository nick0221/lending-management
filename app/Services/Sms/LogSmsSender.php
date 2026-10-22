<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Local and testing sender that records messages without calling a provider.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS message sent.', [
            'to' => $to,
            'message' => $message,
        ]);
    }
}
