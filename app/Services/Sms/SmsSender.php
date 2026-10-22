<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * Send a text message, throwing when the provider rejects it.
     */
    public function send(string $to, string $message): void;
}
