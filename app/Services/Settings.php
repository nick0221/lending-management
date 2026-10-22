<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class Settings
{
    private const CACHE_KEY = 'lending.settings.v2';

    /**
     * Get the persisted settings, falling back to the environment defaults
     * when the settings table has not been migrated yet.
     *
     * @return array{organisation_name: string, currency_symbol: string, default_penalty_rate: string, default_grace_period_days: int, money_rate_limit: int, reports_rate_limit: int, reminders_rate_limit: int, sms_driver: string|null, sms_from: string|null, sms_http_url: string|null, sms_http_method: string|null, sms_http_format: string|null, sms_http_token_header: string|null, sms_http_token_prefix: string|null, sms_http_headers: string|null, sms_http_to_field: string|null, sms_http_message_field: string|null, sms_http_from_field: string|null, sms_http_timeout: int|null, sms_http_connect_timeout: int|null}
     */
    public function values(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function (): array {
                $setting = $this->current();

                return [
                    'organisation_name' => $setting->organisation_name,
                    'currency_symbol' => $setting->currency_symbol,
                    'default_penalty_rate' => $setting->default_penalty_rate,
                    'default_grace_period_days' => (int) $setting->default_grace_period_days,
                    'money_rate_limit' => (int) $setting->money_rate_limit,
                    'reports_rate_limit' => (int) $setting->reports_rate_limit,
                    'reminders_rate_limit' => (int) $setting->reminders_rate_limit,
                    'sms_driver' => $setting->sms_driver,
                    'sms_from' => $setting->sms_from,
                    'sms_http_url' => $setting->sms_http_url,
                    'sms_http_method' => $setting->sms_http_method,
                    'sms_http_format' => $setting->sms_http_format,
                    'sms_http_token_header' => $setting->sms_http_token_header,
                    'sms_http_token_prefix' => $setting->sms_http_token_prefix,
                    'sms_http_headers' => $setting->sms_http_headers,
                    'sms_http_to_field' => $setting->sms_http_to_field,
                    'sms_http_message_field' => $setting->sms_http_message_field,
                    'sms_http_from_field' => $setting->sms_http_from_field,
                    'sms_http_timeout' => $setting->sms_http_timeout,
                    'sms_http_connect_timeout' => $setting->sms_http_connect_timeout,
                ];
            });
        } catch (QueryException) {
            return $this->fallbackValues();
        }
    }

    /**
     * Get the single settings row, creating it with the environment defaults
     * on first use.
     */
    public function current(): Setting
    {
        return Setting::query()->firstOrCreate(
            ['scope' => 'default'],
            $this->defaults(),
        );
    }

    public function organisationName(): string
    {
        return $this->values()['organisation_name'];
    }

    public function currencySymbol(): string
    {
        return $this->values()['currency_symbol'];
    }

    public function defaultPenaltyRate(): string
    {
        return (string) (float) $this->values()['default_penalty_rate'];
    }

    public function defaultGracePeriodDays(): int
    {
        return $this->values()['default_grace_period_days'];
    }

    public function moneyRateLimit(): int
    {
        return $this->values()['money_rate_limit'];
    }

    public function reportsRateLimit(): int
    {
        return $this->values()['reports_rate_limit'];
    }

    public function remindersRateLimit(): int
    {
        return $this->values()['reminders_rate_limit'];
    }

    /**
     * Get the effective SMS gateway configuration.
     *
     * Database settings take over once a driver has been saved from the
     * settings screen. Until then the environment configuration is used.
     *
     * @return array{driver: string, from: string|null, http: array{url: string|null, method: string, format: string, token: string|null, token_header: string, token_prefix: string, headers: array<string, string>, to_field: string, message_field: string, from_field: string, timeout: int, connect_timeout: int}}
     */
    public function smsConfig(): array
    {
        $values = $this->values();

        if (blank($values['sms_driver'])) {
            $http = config('services.sms.http', []);
            $headers = $http['headers'] ?? [];

            return [
                'driver' => strtolower((string) config('services.sms.driver', 'log')),
                'from' => $this->nullableString(config('services.sms.from')),
                'http' => [
                    'url' => $this->nullableString($http['url'] ?? null),
                    'method' => strtoupper((string) ($http['method'] ?? 'POST')),
                    'format' => strtolower((string) ($http['format'] ?? 'json')),
                    'token' => $this->nullableString($http['token'] ?? null),
                    'token_header' => (string) (($http['token_header'] ?? null) ?: 'Authorization'),
                    'token_prefix' => (string) ($http['token_prefix'] ?? 'Bearer '),
                    'headers' => is_array($headers) ? $headers : [],
                    'to_field' => (string) ($http['to_field'] ?? 'to'),
                    'message_field' => (string) ($http['message_field'] ?? 'message'),
                    'from_field' => (string) ($http['from_field'] ?? 'from'),
                    'timeout' => (int) ($http['timeout'] ?? 10),
                    'connect_timeout' => (int) ($http['connect_timeout'] ?? 3),
                ],
            ];
        }

        $headers = is_string($values['sms_http_headers']) && $values['sms_http_headers'] !== ''
            ? json_decode($values['sms_http_headers'], true)
            : [];

        return [
            'driver' => strtolower((string) $values['sms_driver']),
            'from' => $this->nullableString($values['sms_from']),
            'http' => [
                'url' => $this->nullableString($values['sms_http_url']),
                'method' => strtoupper($values['sms_http_method'] ?: 'POST'),
                'format' => strtolower($values['sms_http_format'] ?: 'json'),
                'token' => $this->nullableString($this->current()->sms_http_token),
                'token_header' => $values['sms_http_token_header'] ?: 'Authorization',
                'token_prefix' => $values['sms_http_token_prefix'] ?? 'Bearer ',
                'headers' => is_array($headers) ? $headers : [],
                'to_field' => $values['sms_http_to_field'] ?: 'to',
                'message_field' => $values['sms_http_message_field'] ?: 'message',
                'from_field' => $values['sms_http_from_field'] ?: 'from',
                'timeout' => max((int) ($values['sms_http_timeout'] ?? 10), 1),
                'connect_timeout' => max((int) ($values['sms_http_connect_timeout'] ?? 3), 1),
            ],
        ];
    }

    /**
     * Forget the cached values after the settings row changes.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'scope' => 'default',
            'organisation_name' => (string) config('app.name'),
            'currency_symbol' => '$',
            'default_penalty_rate' => 0,
            'default_grace_period_days' => 0,
            'money_rate_limit' => (int) config('lending.rate_limits.money', 30),
            'reports_rate_limit' => (int) config('lending.rate_limits.reports', 10),
            'reminders_rate_limit' => (int) config('lending.rate_limits.reminders', 10),
            'sms_driver' => null,
            'sms_from' => null,
            'sms_http_url' => null,
            'sms_http_method' => null,
            'sms_http_format' => null,
            'sms_http_token_header' => null,
            'sms_http_token_prefix' => null,
            'sms_http_headers' => null,
            'sms_http_to_field' => null,
            'sms_http_message_field' => null,
            'sms_http_from_field' => null,
            'sms_http_timeout' => null,
            'sms_http_connect_timeout' => null,
        ];
    }

    /**
     * @return array{organisation_name: string, currency_symbol: string, default_penalty_rate: string, default_grace_period_days: int, money_rate_limit: int, reports_rate_limit: int, reminders_rate_limit: int, sms_driver: string|null, sms_from: string|null, sms_http_url: string|null, sms_http_method: string|null, sms_http_format: string|null, sms_http_token_header: string|null, sms_http_token_prefix: string|null, sms_http_headers: string|null, sms_http_to_field: string|null, sms_http_message_field: string|null, sms_http_from_field: string|null, sms_http_timeout: int|null, sms_http_connect_timeout: int|null}
     */
    private function fallbackValues(): array
    {
        return [
            'organisation_name' => (string) config('app.name'),
            'currency_symbol' => '$',
            'default_penalty_rate' => '0',
            'default_grace_period_days' => 0,
            'money_rate_limit' => (int) config('lending.rate_limits.money', 30),
            'reports_rate_limit' => (int) config('lending.rate_limits.reports', 10),
            'reminders_rate_limit' => (int) config('lending.rate_limits.reminders', 10),
            'sms_driver' => null,
            'sms_from' => null,
            'sms_http_url' => null,
            'sms_http_method' => null,
            'sms_http_format' => null,
            'sms_http_token_header' => null,
            'sms_http_token_prefix' => null,
            'sms_http_headers' => null,
            'sms_http_to_field' => null,
            'sms_http_message_field' => null,
            'sms_http_from_field' => null,
            'sms_http_timeout' => null,
            'sms_http_connect_timeout' => null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return filled($value) ? (string) $value : null;
    }
}
