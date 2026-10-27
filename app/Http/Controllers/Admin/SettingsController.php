<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestSmsRequest;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Settings;
use App\Services\Sms\SmsSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    /**
     * Show the organisation and endpoint settings.
     */
    public function edit(Settings $settings): Response
    {
        $setting = $settings->current();
        $sms = $settings->smsConfig();

        return Inertia::render('admin/settings/edit', [
            'settings' => [
                'organisation_name' => $setting->organisation_name,
                'currency_symbol' => $setting->currency_symbol,
                'default_penalty_rate' => (string) (float) $setting->default_penalty_rate,
                'default_grace_period_days' => (string) $setting->default_grace_period_days,
                'money_rate_limit' => (string) $setting->money_rate_limit,
                'reports_rate_limit' => (string) $setting->reports_rate_limit,
                'reminders_rate_limit' => (string) $setting->reminders_rate_limit,
                'sms_driver' => $sms['driver'],
                'sms_from' => $sms['from'] ?? '',
                'sms_http_url' => $sms['http']['url'] ?? '',
                'sms_http_method' => $sms['http']['method'],
                'sms_http_format' => $sms['http']['format'],
                'sms_http_token_configured' => filled($sms['http']['token']),
                'sms_http_token_header' => $sms['http']['token_header'],
                'sms_http_token_prefix' => $sms['http']['token_prefix'],
                'sms_http_headers' => $sms['http']['headers'] === []
                    ? '{}'
                    : (string) json_encode(
                        $sms['http']['headers'],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                    ),
                'sms_http_to_field' => $sms['http']['to_field'],
                'sms_http_message_field' => $sms['http']['message_field'],
                'sms_http_from_field' => $sms['http']['from_field'],
                'sms_http_timeout' => (string) $sms['http']['timeout'],
                'sms_http_connect_timeout' => (string) $sms['http']['connect_timeout'],
            ],
        ]);
    }

    /**
     * Update the organisation and endpoint settings.
     */
    public function update(UpdateSettingsRequest $request, Settings $settings): RedirectResponse
    {
        $data = $request->validated();
        $clearToken = (bool) ($data['sms_http_clear_token'] ?? false);

        $data['sms_http_token_prefix'] = (string) ($data['sms_http_token_prefix'] ?? '');

        unset($data['sms_http_clear_token']);

        if ($clearToken) {
            $data['sms_http_token'] = null;
        } elseif (blank($data['sms_http_token'] ?? null)) {
            $data['sms_http_token'] = $settings->smsConfig()['http']['token'];
        }

        $settings->current()->update($data);

        $settings->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings updated.')]);

        return to_route('admin.settings.edit');
    }

    /**
     * Send a one-off message through the configured SMS gateway.
     */
    public function sendTestSms(
        SendTestSmsRequest $request,
        Settings $settings,
        SmsSender $sms,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $sms->send($validated['phone'], $validated['message']);
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Test SMS failed: :error', [
                    'error' => Str::limit($exception->getMessage(), 300),
                ]),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $settings->smsConfig()['driver'] === 'log'
                ? __('Test message written to the application log. No SMS was delivered.')
                : __('Test SMS sent to :phone.', ['phone' => $validated['phone']]),
        ]);

        return back();
    }
}
