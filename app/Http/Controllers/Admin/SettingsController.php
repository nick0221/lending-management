<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Show the organisation and endpoint settings.
     */
    public function edit(Settings $settings): Response
    {
        $setting = $settings->current();

        return Inertia::render('admin/settings/edit', [
            'settings' => [
                'organisation_name' => $setting->organisation_name,
                'currency_symbol' => $setting->currency_symbol,
                'default_penalty_rate' => (string) (float) $setting->default_penalty_rate,
                'default_grace_period_days' => (string) $setting->default_grace_period_days,
                'money_rate_limit' => (string) $setting->money_rate_limit,
                'reports_rate_limit' => (string) $setting->reports_rate_limit,
                'reminders_rate_limit' => (string) $setting->reminders_rate_limit,
            ],
        ]);
    }

    /**
     * Update the organisation and endpoint settings.
     */
    public function update(UpdateSettingsRequest $request, Settings $settings): RedirectResponse
    {
        $settings->current()->update($request->validated());

        $settings->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings updated.')]);

        return to_route('admin.settings.edit');
    }
}
