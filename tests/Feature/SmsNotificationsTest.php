<?php

use App\Enums\PaymentMethod;
use App\Models\Borrower;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\LoanOverdue;
use App\Notifications\LoanStatement;
use App\Services\RepaymentService;
use App\Services\Settings;
use App\Services\Sms\SmsSender;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

final class RecordingSmsSender implements SmsSender
{
    /**
     * @var array<int, array{to: string, message: string}>
     */
    public array $messages = [];

    public function send(string $to, string $message): void
    {
        $this->messages[] = [
            'to' => $to,
            'message' => $message,
        ];
    }
}

test('due reminders send email and SMS when both channels are enabled', function () {
    Mail::fake();
    $this->travelTo('2026-06-01');

    $sms = new RecordingSmsSender;
    $this->app->instance(SmsSender::class, $sms);

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 0, 'due_date' => '2026-06-04'],
    ]);
    $loan->borrower->update([
        'email' => 'borrower@example.com',
        'notify_sms' => true,
    ]);

    $this->artisan('loans:send-due-reminders')->assertSuccessful();

    expect($sms->messages)->toHaveCount(1)
        ->and($sms->messages[0]['to'])->toBe($loan->borrower->phone)
        ->and($sms->messages[0]['message'])->toContain('Loan #'.$loan->id);

    expect(NotificationLog::query()->where('channel', NotificationLog::CHANNEL_MAIL)->firstOrFail()->status)
        ->toBe(NotificationLog::STATUS_SENT)
        ->and(NotificationLog::query()->where('channel', NotificationLog::CHANNEL_SMS)->firstOrFail()->status)
        ->toBe(NotificationLog::STATUS_SENT);
});

test('SMS works without an email address', function () {
    Mail::fake();
    $this->travelTo('2026-06-01');

    $sms = new RecordingSmsSender;
    $this->app->instance(SmsSender::class, $sms);

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);
    $loan->borrower->update([
        'email' => null,
        'notify_sms' => true,
    ]);

    $this->artisan('loans:send-overdue-notices')->assertSuccessful();

    expect($sms->messages)->toHaveCount(1)
        ->and($sms->messages[0]['message'])->toContain('overdue');

    expect(NotificationLog::query()->where('channel', NotificationLog::CHANNEL_SMS)->count())->toBe(1)
        ->and(NotificationLog::query()->where('channel', NotificationLog::CHANNEL_MAIL)->count())->toBe(0);
});

test('receipt SMS uses the payment amount and remaining balance', function () {
    Mail::fake();

    $sms = new RecordingSmsSender;
    $this->app->instance(SmsSender::class, $sms);

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);
    $loan->borrower->update(['notify_sms' => true]);

    $actor = User::factory()->admin()->create();

    app(RepaymentService::class)->recordPayment($loan, $actor, 50, PaymentMethod::Cash);

    expect($sms->messages)->toHaveCount(1)
        ->and($sms->messages[0]['message'])->toContain('Payment of 50.00')
        ->and($sms->messages[0]['message'])->toContain('Remaining balance: 70.00');
});

test('SMS stays off until the borrower opts in', function () {
    Mail::fake();

    $sms = new RecordingSmsSender;
    $this->app->instance(SmsSender::class, $sms);

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);
    $loan->borrower->update(['email' => 'borrower@example.com']);

    $this->artisan('loans:send-overdue-notices')->assertSuccessful();

    expect($sms->messages)->toBeEmpty()
        ->and(NotificationLog::query()->where('channel', NotificationLog::CHANNEL_MAIL)->count())->toBe(1);
});

test('the HTTP SMS sender uses gateway settings saved in the UI', function () {
    $settings = app(Settings::class);

    $settings->current()->update([
        'sms_driver' => 'http',
        'sms_from' => 'LENDER',
        'sms_http_url' => 'https://sms.test/send',
        'sms_http_method' => 'POST',
        'sms_http_format' => 'json',
        'sms_http_token' => 'secret',
        'sms_http_token_header' => 'X-Api-Key',
        'sms_http_token_prefix' => '',
        'sms_http_headers' => '{"X-Tenant":"lending"}',
        'sms_http_to_field' => 'recipient',
        'sms_http_message_field' => 'text',
        'sms_http_from_field' => 'sender',
        'sms_http_timeout' => 10,
        'sms_http_connect_timeout' => 3,
    ]);
    $settings->flush();

    $this->app->forgetInstance(SmsSender::class);
    Http::fake([
        'https://sms.test/send' => Http::response(['ok' => true]),
    ]);

    $this->app->make(SmsSender::class)->send('+263771234567', 'Hello');

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sms.test/send'
        && $request->method() === 'POST'
        && $request['recipient'] === '+263771234567'
        && $request['text'] === 'Hello'
        && $request['sender'] === 'LENDER'
        && $request->hasHeader('X-Api-Key', 'secret')
        && $request->hasHeader('X-Tenant', 'lending'));
});

test('an SMS delivery is marked sent only for its own log', function () {
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    $mailLog = NotificationLog::reserve(
        "loan:{$loan->id}:statement:mail",
        NotificationLog::TYPE_STATEMENT,
        $loan->borrower,
        $loan,
    );
    $smsLog = NotificationLog::reserve(
        "loan:{$loan->id}:statement:sms",
        NotificationLog::TYPE_STATEMENT,
        $loan->borrower,
        $loan,
        NotificationLog::CHANNEL_SMS,
    );

    $notification = (new LoanStatement($loan))
        ->forChannels([NotificationLog::CHANNEL_MAIL, NotificationLog::CHANNEL_SMS])
        ->forLogs([$mailLog, $smsLog]);

    event(new NotificationSent(
        $loan->borrower,
        $notification,
        NotificationLog::CHANNEL_SMS,
    ));

    expect($smsLog->fresh()->status)->toBe(NotificationLog::STATUS_SENT)
        ->and($mailLog->fresh()->status)->toBe(NotificationLog::STATUS_QUEUED);
});

test('staff can resend an SMS notification from the log', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);
    $loan->borrower->update(['notify_sms' => true]);

    $log = NotificationLog::create([
        'key' => "loan:{$loan->id}:overdue:test:sms",
        'type' => NotificationLog::TYPE_OVERDUE,
        'channel' => NotificationLog::CHANNEL_SMS,
        'status' => NotificationLog::STATUS_FAILED,
        'borrower_id' => $loan->borrower_id,
        'loan_id' => $loan->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.loans.notifications.resend', [
            'loan' => $loan,
            'notificationLog' => $log,
        ]))
        ->assertRedirect();

    Notification::assertSentTo(
        $loan->borrower,
        LoanOverdue::class,
        fn (LoanOverdue $notification): bool => $notification->via($loan->borrower) === [NotificationLog::CHANNEL_SMS],
    );

    expect($log->fresh()->status)->toBe(NotificationLog::STATUS_QUEUED);
});

test('the borrower form persists the SMS preference', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.borrowers.store'), [
            'name' => 'SMS Preference Tester',
            'phone' => '+1-555-0101',
            'email' => 'sms-prefs@example.com',
            'kyc_status' => 'pending',
            'notify_due_soon' => '1',
            'notify_overdue' => '1',
            'notify_receipts' => '1',
            'notify_statements' => '1',
            'notify_sms' => '1',
        ])
        ->assertRedirect(route('admin.borrowers.index'));

    $borrower = Borrower::query()->where('email', 'sms-prefs@example.com')->firstOrFail();

    expect($borrower->notify_sms)->toBeTrue();
});

test('the loan page exposes the SMS channel and whether it can be resent', function () {
    $admin = User::factory()->admin()->create();
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);
    $loan->borrower->update(['notify_sms' => true]);

    NotificationLog::create([
        'key' => "loan:{$loan->id}:statement:test:sms",
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => NotificationLog::CHANNEL_SMS,
        'status' => NotificationLog::STATUS_FAILED,
        'borrower_id' => $loan->borrower_id,
        'loan_id' => $loan->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.loans.show', ['loan' => $loan, 'tab' => 'notifications']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notifications.0.channel_label', 'SMS')
            ->where('notifications.0.can_resend', false)
        );
});
