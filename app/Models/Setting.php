<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $scope
 * @property string $organisation_name
 * @property string $currency_symbol
 * @property string $default_penalty_rate
 * @property int $default_grace_period_days
 * @property int $money_rate_limit
 * @property int $reports_rate_limit
 * @property int $reminders_rate_limit
 * @property string|null $sms_driver
 * @property string|null $sms_from
 * @property string|null $sms_http_url
 * @property string|null $sms_http_method
 * @property string|null $sms_http_format
 * @property string|null $sms_http_token
 * @property string|null $sms_http_token_header
 * @property string|null $sms_http_token_prefix
 * @property string|null $sms_http_headers
 * @property string|null $sms_http_to_field
 * @property string|null $sms_http_message_field
 * @property string|null $sms_http_from_field
 * @property int|null $sms_http_timeout
 * @property int|null $sms_http_connect_timeout
 */
#[Fillable([
    'scope',
    'organisation_name',
    'currency_symbol',
    'default_penalty_rate',
    'default_grace_period_days',
    'money_rate_limit',
    'reports_rate_limit',
    'reminders_rate_limit',
    'sms_driver',
    'sms_from',
    'sms_http_url',
    'sms_http_method',
    'sms_http_format',
    'sms_http_token',
    'sms_http_token_header',
    'sms_http_token_prefix',
    'sms_http_headers',
    'sms_http_to_field',
    'sms_http_message_field',
    'sms_http_from_field',
    'sms_http_timeout',
    'sms_http_connect_timeout',
])]
class Setting extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_penalty_rate' => 'decimal:4',
            'default_grace_period_days' => 'integer',
            'money_rate_limit' => 'integer',
            'reports_rate_limit' => 'integer',
            'reminders_rate_limit' => 'integer',
            'sms_http_token' => 'encrypted',
            'sms_http_timeout' => 'integer',
            'sms_http_connect_timeout' => 'integer',
        ];
    }
}
