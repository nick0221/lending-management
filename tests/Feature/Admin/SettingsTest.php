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
        );

    expect(Setting::query()->count())->toBe(1);
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
        ->and(Setting::query()->count())->toBe(1);

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
        ])
        ->assertSessionHasErrors([
            'organisation_name',
            'currency_symbol',
            'default_penalty_rate',
            'default_grace_period_days',
            'money_rate_limit',
            'reports_rate_limit',
            'reminders_rate_limit',
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
