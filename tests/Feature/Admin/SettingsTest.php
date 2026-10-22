<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;

test('super admins can open the settings page', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/edit')
            ->where('settings.organisation_name', config('app.name'))
            ->where('settings.currency_symbol', '$')
            ->where('settings.money_rate_limit', (string) config('lending.rate_limits.money', 30))
            ->where('settings.reports_rate_limit', (string) config('lending.rate_limits.reports', 10))
            ->where('settings.reminders_rate_limit', (string) config('lending.rate_limits.reminders', 10))
            ->where('settings.sms_driver', config('services.sms.driver', 'log'))
            ->where('settings.sms_http_url', config('services.sms.http.url') ?? '')
            ->where('settings.sms_http_method', config('services.sms.http.method', 'POST'))
            ->where('settings.sms_http_format', config('services.sms.http.format', 'json'))
            ->where('settings.sms_http_token_configured', filled(config('services.sms.http.token')))
        );

    expect(Setting::query()->count())->toBe(1);
});

test('SMS settings fall back to the environment until a driver is saved', function () {
    config([
        'services.sms.driver' => 'http',
        'services.sms.from' => 'ENV_LENDER',
        'services.sms.http.url' => 'https://env-sms.example.com/send',
        'services.sms.http.method' => 'PUT',
        'services.sms.http.format' => 'form',
        'services.sms.http.token' => 'env-token',
        'services.sms.http.token_header' => 'X-Env-Key',
        'services.sms.http.token_prefix' => 'Token ',
        'services.sms.http.headers' => ['X-Env' => 'yes'],
        'services.sms.http.to_field' => 'recipient',
        'services.sms.http.message_field' => 'body',
        'services.sms.http.from_field' => 'sender',
        'services.sms.http.timeout' => 15,
        'services.sms.http.connect_timeout' => 5,
    ]);

    $settings = app(Settings::class);
    $settings->flush();

    $sms = $settings->smsConfig();

    expect($sms['driver'])->toBe('http')
        ->and($sms['from'])->toBe('ENV_LENDER')
        ->and($sms['http']['url'])->toBe('https://env-sms.example.com/send')
        ->and($sms['http']['method'])->toBe('PUT')
        ->and($sms['http']['format'])->toBe('form')
        ->and($sms['http']['token'])->toBe('env-token')
        ->and($sms['http']['token_header'])->toBe('X-Env-Key')
        ->and($sms['http']['token_prefix'])->toBe('Token ')
        ->and($sms['http']['headers'])->toBe(['X-Env' => 'yes'])
        ->and($sms['http']['to_field'])->toBe('recipient')
        ->and($sms['http']['message_field'])->toBe('body')
        ->and($sms['http']['from_field'])->toBe('sender')
        ->and($sms['http']['timeout'])->toBe(15)
        ->and($sms['http']['connect_timeout'])->toBe(5);
});

test('super admins can update the organisation settings', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.settings.update'), [
            'organisation_name' => 'Acme Lending',
            'currency_symbol' => '€',
            'default_penalty_rate' => 2.5,
            'default_grace_period_days' => 7,
            'money_rate_limit' => 40,
            'reports_rate_limit' => 15,
            'reminders_rate_limit' => 20,
            'sms_driver' => 'http',
            'sms_from' => 'LENDER',
            'sms_http_url' => 'https://sms.example.com/send',
            'sms_http_method' => 'POST',
            'sms_http_format' => 'json',
            'sms_http_token' => 'secret-token',
            'sms_http_clear_token' => '0',
            'sms_http_token_header' => 'X-Api-Key',
            'sms_http_token_prefix' => '',
            'sms_http_headers' => '{"X-Tenant":"lending"}',
            'sms_http_to_field' => 'recipient',
            'sms_http_message_field' => 'text',
            'sms_http_from_field' => 'sender',
            'sms_http_timeout' => 12,
            'sms_http_connect_timeout' => 4,
        ])
        ->assertRedirect(route('admin.settings.edit'));

    $setting = Setting::query()->firstOrFail();

    expect($setting->organisation_name)->toBe('Acme Lending')
        ->and($setting->currency_symbol)->toBe('€')
        ->and((float) $setting->default_penalty_rate)->toBe(2.5)
        ->and($setting->default_grace_period_days)->toBe(7)
        ->and($setting->money_rate_limit)->toBe(40)
        ->and($setting->reports_rate_limit)->toBe(15)
        ->and($setting->reminders_rate_limit)->toBe(20)
        ->and($setting->sms_driver)->toBe('http')
        ->and($setting->sms_from)->toBe('LENDER')
        ->and($setting->sms_http_url)->toBe('https://sms.example.com/send')
        ->and($setting->sms_http_method)->toBe('POST')
        ->and($setting->sms_http_format)->toBe('json')
        ->and($setting->sms_http_token)->toBe('secret-token')
        ->and($setting->getRawOriginal('sms_http_token'))->not->toBe('secret-token')
        ->and($setting->sms_http_token_header)->toBe('X-Api-Key')
        ->and($setting->sms_http_token_prefix)->toBe('')
        ->and($setting->sms_http_headers)->toBe('{"X-Tenant":"lending"}')
        ->and($setting->sms_http_to_field)->toBe('recipient')
        ->and($setting->sms_http_message_field)->toBe('text')
        ->and($setting->sms_http_from_field)->toBe('sender')
        ->and($setting->sms_http_timeout)->toBe(12)
        ->and($setting->sms_http_connect_timeout)->toBe(4)
        ->and(Setting::query()->count())->toBe(1);

    $this->actingAs($superAdmin)
        ->get(route('admin.settings.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('settings.sms_driver', 'http')
            ->where('settings.sms_http_url', 'https://sms.example.com/send')
            ->where('settings.sms_http_token_configured', true)
            ->where('settings.sms_http_to_field', 'recipient')
        );

    $this->actingAs($superAdmin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('name', 'Acme Lending')
            ->where('currency_symbol', '€')
        );
});

test('admins cannot open or update the settings page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.settings.edit'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'organisation_name' => 'Not allowed',
            'currency_symbol' => '$',
            'default_penalty_rate' => 0,
            'default_grace_period_days' => 0,
            'money_rate_limit' => 30,
            'reports_rate_limit' => 10,
            'reminders_rate_limit' => 10,
        ])
        ->assertForbidden();

    expect(Setting::query()->where('organisation_name', 'Not allowed')->exists())->toBeFalse();
});

test('a blank SMS token keeps the stored token and the clear option removes it', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $settings = app(Settings::class);

    $settings->current()->update([
        'sms_driver' => 'http',
        'sms_http_url' => 'https://sms.example.com/send',
        'sms_http_method' => 'POST',
        'sms_http_format' => 'json',
        'sms_http_token' => 'first-token',
        'sms_http_token_header' => 'Authorization',
        'sms_http_token_prefix' => 'Bearer ',
        'sms_http_headers' => '{}',
        'sms_http_to_field' => 'to',
        'sms_http_message_field' => 'message',
        'sms_http_from_field' => 'from',
        'sms_http_timeout' => 10,
        'sms_http_connect_timeout' => 3,
    ]);
    $settings->flush();

    $payload = [
        'organisation_name' => config('app.name'),
        'currency_symbol' => '$',
        'default_penalty_rate' => 0,
        'default_grace_period_days' => 0,
        'money_rate_limit' => 30,
        'reports_rate_limit' => 10,
        'reminders_rate_limit' => 10,
        'sms_driver' => 'http',
        'sms_from' => '',
        'sms_http_url' => 'https://sms.example.com/send',
        'sms_http_method' => 'POST',
        'sms_http_format' => 'json',
        'sms_http_token' => '',
        'sms_http_clear_token' => '0',
        'sms_http_token_header' => 'Authorization',
        'sms_http_token_prefix' => 'Bearer ',
        'sms_http_headers' => '{}',
        'sms_http_to_field' => 'to',
        'sms_http_message_field' => 'message',
        'sms_http_from_field' => 'from',
        'sms_http_timeout' => 10,
        'sms_http_connect_timeout' => 3,
    ];

    $this->actingAs($superAdmin)
        ->put(route('admin.settings.update'), $payload)
        ->assertRedirect(route('admin.settings.edit'));

    expect(Setting::query()->firstOrFail()->sms_http_token)->toBe('first-token');

    $this->actingAs($superAdmin)
        ->put(route('admin.settings.update'), [
            ...$payload,
            'sms_http_clear_token' => '1',
        ])
        ->assertRedirect(route('admin.settings.edit'));

    expect(Setting::query()->firstOrFail()->sms_http_token)->toBeNull();
});

test('settings values are validated', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->from(route('admin.settings.edit'))
        ->put(route('admin.settings.update'), [
            'organisation_name' => '',
            'currency_symbol' => '',
            'default_penalty_rate' => -1,
            'default_grace_period_days' => -1,
            'money_rate_limit' => 0,
            'reports_rate_limit' => 0,
            'reminders_rate_limit' => 0,
            'sms_driver' => 'ftp',
            'sms_from' => str_repeat('a', 65),
            'sms_http_url' => 'not-a-url',
            'sms_http_method' => 'DELETE',
            'sms_http_format' => 'xml',
            'sms_http_token_header' => '',
            'sms_http_token_prefix' => str_repeat('b', 101),
            'sms_http_headers' => '["not","an","object"]',
            'sms_http_to_field' => '',
            'sms_http_message_field' => '',
            'sms_http_from_field' => '',
            'sms_http_timeout' => 0,
            'sms_http_connect_timeout' => 31,
        ])
        ->assertSessionHasErrors([
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
            'sms_http_token_header',
            'sms_http_token_prefix',
            'sms_http_headers',
            'sms_http_to_field',
            'sms_http_message_field',
            'sms_http_from_field',
            'sms_http_timeout',
            'sms_http_connect_timeout',
        ]);
});

test('new loan products inherit the configured penalty and grace defaults', function () {
    $settings = app(Settings::class);

    $settings->current()->update([
        'default_penalty_rate' => 2.5,
        'default_grace_period_days' => 7,
    ]);
    $settings->flush();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.loan-products.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('defaults.penalty_rate', '2.5')
            ->where('defaults.grace_period_days', '7')
        );
});
