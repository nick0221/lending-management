<?php

use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\LoanDueSoon;
use App\Notifications\LoanOverdue;
use App\Notifications\LoanStatement;
use App\Notifications\PaymentReceived;
use App\Services\RepaymentService;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Events\NotificationSkipped;
use Illuminate\Support\Facades\Notification;

/**
 * Build an active loan with a borrower address and the given schedule.
 *
 * @param  array<int, array{principal: float|int, interest: float|int, fee: float|int, due_date: string}>  $installments
 */
function notificationResendLoan(array $installments): Loan
{
    $loan = activeLoanWithDatedSchedule($installments);
    $loan->borrower->update(['email' => 'resend@example.com']);

    return $loan->fresh('borrower');
}

test('staff can resend a statement from the notification log', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 10, 'fee' => 5, 'due_date' => '2026-07-01'],
    ]);

    $log = NotificationLog::create([
        'key' => "loan:{$loan->id}:statement:test",
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => 'mail',
        'status' => NotificationLog::STATUS_FAILED,
        'error' => 'SMTP timeout',
        'borrower_id' => $loan->borrower_id,
        'loan_id' => $loan->id,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.loans.show', ['loan' => $loan, 'tab' => 'notifications']))
        ->post(route('admin.loans.notifications.resend', [
            'loan' => $loan,
            'notificationLog' => $log,
        ]))
        ->assertRedirect(route('admin.loans.show', ['loan' => $loan, 'tab' => 'notifications']));

    Notification::assertSentTo($loan->borrower, LoanStatement::class);

    expect($log->fresh()->status)->toBe(NotificationLog::STATUS_QUEUED)
        ->and($log->fresh()->error)->toBeNull()
        ->and($log->fresh()->sent_at)->toBeNull();
});

test('staff can resend an overdue notice from the log', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);

    $log = NotificationLog::create([
        'key' => "loan:{$loan->id}:overdue:2026-06-01",
        'type' => NotificationLog::TYPE_OVERDUE,
        'channel' => 'mail',
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
        fn (LoanOverdue $notification): bool => $notification->installments->count() === 1,
    );

    expect($log->fresh()->status)->toBe(NotificationLog::STATUS_QUEUED);
});

test('staff can resend a receipt from the log', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    app(RepaymentService::class)->recordPayment($loan, $admin, 50, PaymentMethod::Cash);

    $log = NotificationLog::query()
        ->where('type', NotificationLog::TYPE_RECEIPT)
        ->firstOrFail();
    $log->markFailed('SMTP timeout');

    $this->actingAs($admin)
        ->post(route('admin.loans.notifications.resend', [
            'loan' => $loan,
            'notificationLog' => $log,
        ]))
        ->assertRedirect();

    Notification::assertSentTo(
        $loan->borrower,
        PaymentReceived::class,
        fn (PaymentReceived $notification): bool => (float) $notification->payment->amount === 50.0,
    );

    expect($log->fresh()->status)->toBe(NotificationLog::STATUS_QUEUED);
});

test('staff can resend a due soon reminder from the log', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-04'],
    ]);

    $installment = $loan->installments()->firstOrFail();

    $log = NotificationLog::create([
        'key' => "installment:{$installment->id}:due_soon:2026-06-04",
        'type' => NotificationLog::TYPE_DUE_SOON,
        'channel' => 'mail',
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
        LoanDueSoon::class,
        fn (LoanDueSoon $notification): bool => $notification->installment->id === $installment->id,
    );

    expect($log->fresh()->status)->toBe(NotificationLog::STATUS_QUEUED);
});

test('resending respects the borrower preference', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);
    $loan->borrower->update(['notify_statements' => false]);

    $log = NotificationLog::create([
        'key' => "loan:{$loan->id}:statement:test",
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => 'mail',
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

    Notification::assertNothingSent();

    expect($log->fresh()->status)->toBe(NotificationLog::STATUS_SKIPPED)
        ->and($log->fresh()->error)->toContain('disabled');
});

test('resending an overdue notice without overdue installments is skipped', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2099-07-01'],
    ]);

    $log = NotificationLog::create([
        'key' => "loan:{$loan->id}:overdue:test",
        'type' => NotificationLog::TYPE_OVERDUE,
        'channel' => 'mail',
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

    Notification::assertNothingSent();

    expect($log->fresh()->status)->toBe(NotificationLog::STATUS_SKIPPED)
        ->and($log->fresh()->error)->toContain('overdue');
});

test('regular users cannot resend notifications', function () {
    Notification::fake();

    $user = User::factory()->create();
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    $log = NotificationLog::create([
        'key' => "loan:{$loan->id}:statement:test",
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => 'mail',
        'status' => NotificationLog::STATUS_FAILED,
        'borrower_id' => $loan->borrower_id,
        'loan_id' => $loan->id,
    ]);

    $this->actingAs($user)
        ->post(route('admin.loans.notifications.resend', [
            'loan' => $loan,
            'notificationLog' => $log,
        ]))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('a notification cannot be resent through another loan', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);
    $otherLoan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    $log = NotificationLog::create([
        'key' => "loan:{$loan->id}:statement:test",
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => 'mail',
        'status' => NotificationLog::STATUS_FAILED,
        'borrower_id' => $loan->borrower_id,
        'loan_id' => $loan->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.loans.notifications.resend', [
            'loan' => $otherLoan,
            'notificationLog' => $log,
        ]))
        ->assertNotFound();

    Notification::assertNothingSent();
});

test('notification events update the delivery log', function () {
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    $sentLog = NotificationLog::reserve(
        "loan:{$loan->id}:statement:sent",
        NotificationLog::TYPE_STATEMENT,
        $loan->borrower,
        $loan,
    );
    $sentNotification = (new LoanStatement($loan))->forLog($sentLog);

    event(new NotificationSent($loan->borrower, $sentNotification, 'mail'));

    expect($sentLog->fresh()->status)->toBe(NotificationLog::STATUS_SENT)
        ->and($sentLog->fresh()->sent_at)->not->toBeNull();

    $failedLog = NotificationLog::reserve(
        "loan:{$loan->id}:statement:failed",
        NotificationLog::TYPE_STATEMENT,
        $loan->borrower,
        $loan,
    );
    $failedNotification = (new LoanStatement($loan))->forLog($failedLog);

    event(new NotificationFailed(
        $loan->borrower,
        $failedNotification,
        'mail',
        ['exception' => new RuntimeException('SMTP down')],
    ));

    expect($failedLog->fresh()->status)->toBe(NotificationLog::STATUS_FAILED)
        ->and($failedLog->fresh()->error)->toBe('SMTP down');

    $skippedLog = NotificationLog::reserve(
        "loan:{$loan->id}:statement:skipped",
        NotificationLog::TYPE_STATEMENT,
        $loan->borrower,
        $loan,
    );
    $skippedNotification = (new LoanStatement($loan))->forLog($skippedLog);

    event(new NotificationSkipped($loan->borrower, $skippedNotification, 'mail'));

    expect($skippedLog->fresh()->status)->toBe(NotificationLog::STATUS_SKIPPED);
});

test('the loan page exposes delivery status and resend ability', function () {
    $admin = User::factory()->admin()->create();
    $loan = notificationResendLoan([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    NotificationLog::create([
        'key' => "loan:{$loan->id}:statement:test",
        'type' => NotificationLog::TYPE_STATEMENT,
        'channel' => 'mail',
        'status' => NotificationLog::STATUS_FAILED,
        'error' => 'SMTP timeout',
        'borrower_id' => $loan->borrower_id,
        'loan_id' => $loan->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.loans.show', ['loan' => $loan, 'tab' => 'notifications']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notifications.0.status', 'failed')
            ->where('notifications.0.status_label', 'Failed')
            ->where('notifications.0.error', 'SMTP timeout')
            ->where('notifications.0.sent_at', null)
            ->where('notifications.0.can_resend', true)
        );
});
