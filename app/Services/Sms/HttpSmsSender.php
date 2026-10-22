<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends SMS messages to a configurable JSON or form HTTP gateway.
 */
class HttpSmsSender implements SmsSender
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private readonly ?string $url,
        private readonly string $method = 'POST',
        private readonly string $format = 'json',
        private readonly ?string $token = null,
        private readonly string $tokenHeader = 'Authorization',
        private readonly string $tokenPrefix = 'Bearer ',
        private readonly array $headers = [],
        private readonly string $toField = 'to',
        private readonly string $messageField = 'message',
        private readonly ?string $from = null,
        private readonly string $fromField = 'from',
        private readonly int $timeout = 10,
        private readonly int $connectTimeout = 3,
    ) {}

    /**
     * Send the message through the configured gateway.
     */
    public function send(string $to, string $message): void
    {
        if (blank($this->url)) {
            throw new RuntimeException(__('The SMS gateway URL is not configured.'));
        }

        $payload = [
            $this->toField => $to,
            $this->messageField => $message,
        ];

        if (filled($this->from)) {
            $payload[$this->fromField] = $this->from;
        }

        $headers = $this->headers;

        if (filled($this->token)) {
            $headers[$this->tokenHeader] = $this->tokenPrefix.$this->token;
        }

        $method = strtoupper($this->method ?: 'POST');
        $format = strtolower($this->format ?: 'json');

        Http::withHeaders($headers)
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->acceptJson()
            ->send(
                $method,
                $this->url,
                $format === 'form'
                    ? ['form_params' => $payload]
                    : ['json' => $payload],
            )
            ->throw();
    }
}
