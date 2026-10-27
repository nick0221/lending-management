<?php

use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Settings;

test('super admins can see the system status', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/system/index')
            ->where('app.laravel', app()->version())
            ->where('app.php', PHP_VERSION)
            ->where('database.connection', config('database.default'))
            ->where('database.status', 'Connected')
            ->where('queue.connection', config('queue.default'))
            ->where('mail.mailer', config('mail.default'))
            ->has('scheduled')
        );
});

test('the system status reports the SMS gateway and recent delivery counts', function () {
    $this->travelTo('2026-10-10 09:00:00');

    NotificationLog::create([
        'key' => 'sms-old-sent',
        'type' => NotificationLog::TYPE_RECEIPT,
        'channel' => NotificationLog::CHANNEL_SMS,
        'status' => NotificationLog::STATUS_SENT,
        'sent_at' => now(),
    ]);

    $this->travelTo('2026-10-27 12:00:00');

    $settings = app(Settings::class);
    $settings->current()->update([
        'sms_driver' => 'http',
        'sms_from' => 'LENDING',
        'sms_http_url' => 'https://sms.example.com/send?token=secret-token',
        'sms_http_method' => 'POST',
        'sms_http_format' => 'json',
        'sms_http_token' => 'secret-token',
    ]);
    $settings->flush();

    $logs = [
        ['key' => 'sms-sent-1', 'status' => NotificationLog::STATUS_SENT, 'sent_at' => now()->subHours(2)],
        ['key' => 'sms-sent-2', 'status' => NotificationLog::STATUS_SENT, 'sent_at' => now()->subHour()],
        ['key' => 'sms-failed', 'status' => NotificationLog::STATUS_FAILED, 'sent_at' => null],
        ['key' => 'sms-skipped', 'status' => NotificationLog::STATUS_SKIPPED, 'sent_at' => null],
    ];

    foreach ($logs as $log) {
        NotificationLog::create([
            'type' => NotificationLog::TYPE_OVERDUE,
            'channel' => NotificationLog::CHANNEL_SMS,
            ...$log,
            ...($log['key'] === 'sms-failed' ? ['error' => 'Gateway rejected the message.'] : []),
        ]);
    }

    NotificationLog::create([
        'key' => 'mail-sent',
        'type' => NotificationLog::TYPE_RECEIPT,
        'channel' => NotificationLog::CHANNEL_MAIL,
        'status' => NotificationLog::STATUS_SENT,
        'sent_at' => now(),
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertDontSee('secret-token')
        ->assertInertia(fn ($page) => $page
            ->where('sms.driver', 'http')
            ->where('sms.configured', true)
            ->where('sms.endpoint', 'sms.example.com')
            ->where('sms.method', 'POST')
            ->where('sms.format', 'json')
            ->where('sms.from', 'LENDING')
            ->where('sms.window_days', 7)
            ->where('sms.deliveries.sent', 2)
            ->where('sms.deliveries.failed', 1)
            ->where('sms.deliveries.skipped', 1)
            ->where('sms.deliveries.queued', 0)
            ->where('sms.last_failure.message', 'Gateway rejected the message.')
            ->whereNot('sms.last_sent_at', null)
        );
});

test('the system status flags an HTTP gateway without an endpoint', function () {
    $settings = app(Settings::class);
    $settings->current()->update([
        'sms_driver' => 'http',
        'sms_http_url' => null,
    ]);
    $settings->flush();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sms.driver', 'http')
            ->where('sms.configured', false)
            ->where('sms.endpoint', null)
        );
});

test('admins cannot see the system status', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.system.index'))
        ->assertForbidden();
});
