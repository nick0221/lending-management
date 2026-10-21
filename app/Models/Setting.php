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
        ];
    }
}
