<?php

use App\Models\Loan;
use App\Models\NotificationLog;
use App\Models\User;

/**
 * Create a delivery log for the given borrower's loan.
 */
function deliveryLog(Loan $loan, array $attributes = []): NotificationLog
{
    return NotificationLog::create([
        'key' => 'test:'.fake()->unique()->uuid(),
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => NotificationLog::CHANNEL_MAIL,
        'status' => NotificationLog::STATUS_SENT,
        'borrower_id' => $loan->borrower_id,
        'loan_id' => $loan->id,
        'sent_at' => now(),
        ...$attributes,
    ]);
}

test('the notification register lists every delivery newest first', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->create();
    $loan->borrower->update(['name' => 'Ada Logs', 'phone' => '+1-555-0199']);

    $older = deliveryLog($loan, [
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => NotificationLog::CHANNEL_MAIL,
        'status' => NotificationLog::STATUS_SENT,
    ]);

    $newer = deliveryLog($loan, [
        'type' => NotificationLog::TYPE_OVERDUE,
        'channel' => NotificationLog::CHANNEL_SMS,
        'status' => NotificationLog::STATUS_FAILED,
        'error' => 'Gateway timeout',
        'sent_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/notifications/index')
            ->has('logs.data', 2)
            ->where('logs.data.0.id', $newer->id)
            ->where('logs.data.0.borrower_name', 'Ada Logs')
            ->where('logs.data.0.type_label', 'Overdue')
            ->where('logs.data.0.channel_label', 'SMS')
            ->where('logs.data.0.status_label', 'Failed')
            ->where('logs.data.0.error', 'Gateway timeout')
            ->where('logs.data.0.loan_id', $loan->id)
            ->where('logs.data.1.id', $older->id)
            ->where('counts.all', 2)
            ->where('counts.sent', 1)
            ->where('counts.failed', 1)
            ->where('counts.skipped', 0)
            ->where('counts.queued', 0)
            ->where('currentStatus', null)
        );
});

test('the notification register filters by status, channel, type and search', function () {
    $admin = User::factory()->admin()->create();

    $matchingLoan = Loan::factory()->create();
    $matchingLoan->borrower->update([
        'name' => 'Ada Logs',
        'phone' => '+1-555-0100',
    ]);

    $matching = deliveryLog($matchingLoan, [
        'type' => NotificationLog::TYPE_RECEIPT,
        'channel' => NotificationLog::CHANNEL_SMS,
        'status' => NotificationLog::STATUS_FAILED,
    ]);

    $otherLoan = Loan::factory()->create();
    $otherLoan->borrower->update(['name' => 'Grace Other']);

    deliveryLog($otherLoan, [
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => NotificationLog::CHANNEL_MAIL,
        'status' => NotificationLog::STATUS_SENT,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.notifications.index', ['status' => 'failed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
            ->where('currentStatus', 'failed')
        );

    $this->actingAs($admin)
        ->get(route('admin.notifications.index', [
            'channel' => NotificationLog::CHANNEL_SMS,
            'type' => NotificationLog::TYPE_RECEIPT,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
            ->where('currentChannel', NotificationLog::CHANNEL_SMS)
            ->where('currentType', NotificationLog::TYPE_RECEIPT)
        );

    $this->actingAs($admin)
        ->get(route('admin.notifications.index', ['search' => 'Ada']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
            ->where('search', 'Ada')
        );
});

test('the notification register exposes resend only when the borrower accepts the channel', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->create();
    $loan->borrower->update([
        'notify_statements' => true,
        'notify_sms' => true,
    ]);

    $email = deliveryLog($loan, [
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => NotificationLog::CHANNEL_MAIL,
        'status' => NotificationLog::STATUS_FAILED,
    ]);

    $sms = deliveryLog($loan, [
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => NotificationLog::CHANNEL_SMS,
        'status' => NotificationLog::STATUS_FAILED,
    ]);

    $loan->borrower->update(['notify_sms' => false]);

    $this->actingAs($admin)
        ->get(route('admin.notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 2)
            ->where('logs.data.0.id', $sms->id)
            ->where('logs.data.0.can_resend', false)
            ->where('logs.data.1.id', $email->id)
            ->where('logs.data.1.can_resend', true)
        );
});

test('the notification register exports the filtered deliveries as a CSV', function () {
    $admin = User::factory()->admin()->create();

    $matchingLoan = Loan::factory()->create();
    $matchingLoan->borrower->update([
        'name' => 'Ada Logs',
        'phone' => '+1-555-0100',
    ]);

    deliveryLog($matchingLoan, [
        'type' => NotificationLog::TYPE_OVERDUE,
        'channel' => NotificationLog::CHANNEL_SMS,
        'status' => NotificationLog::STATUS_FAILED,
        'error' => 'Gateway timeout',
    ]);

    $otherLoan = Loan::factory()->create();
    $otherLoan->borrower->update(['name' => 'Grace Other']);

    deliveryLog($otherLoan, [
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => NotificationLog::CHANNEL_MAIL,
        'status' => NotificationLog::STATUS_SENT,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.notifications.export', ['channel' => 'sms']));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Recorded at')
        ->toContain('Borrower')
        ->toContain('Ada Logs')
        ->toContain('Overdue')
        ->toContain('SMS')
        ->toContain('Failed')
        ->toContain('Gateway timeout')
        ->not->toContain('Grace Other');
});

test('regular users cannot open or export the notification register', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.notifications.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.notifications.export'))
        ->assertForbidden();
});
