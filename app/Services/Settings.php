<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class Settings
{
    private const CACHE_KEY = 'lending.settings';

    /**
     * Get the persisted settings, falling back to the environment defaults
     * when the settings table has not been migrated yet.
     *
     * @return array{organisation_name: string, currency_symbol: string, default_penalty_rate: string, default_grace_period_days: int, money_rate_limit: int, reports_rate_limit: int, reminders_rate_limit: int}
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
        ];
    }

    /**
     * @return array{organisation_name: string, currency_symbol: string, default_penalty_rate: string, default_grace_period_days: int, money_rate_limit: int, reports_rate_limit: int, reminders_rate_limit: int}
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
        ];
    }
}
