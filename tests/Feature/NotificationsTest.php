<?php

use App\Enums\PaymentMethod;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\LoanDueSoon;
use App\Notifications\LoanOverdue;
use App\Notifications\LoanStatement;
use App\Notifications\PaymentReceived;
use App\Services\RepaymentService;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;

/**
 * Give the loan's borrower a real address so mail assertions are meaningful.
 */
function withBorrowerEmail(Loan $loan, string $email = 'borrower@example.com'): Loan
{
    $loan->borrower->update(['email' => $email]);

    return $loan->fresh('borrower');
}

test('the due reminder command emails installments inside the window', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    config(['lending.due_reminder_days' => 3]);

    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 0, 'due_date' => '2026-06-04'],
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-09'],
    ]));

    $this->artisan('loans:send-due-reminders')->assertSuccessful();

    Notification::assertSentTimes(LoanDueSoon::class, 1);
    Notification::assertSentTo(
        $loan->borrower,
        LoanDueSoon::class,
        fn (LoanDueSoon $notification): bool => $notification->installment->sequence === 1,
    );

    expect(NotificationLog::query()->where('type', NotificationLog::TYPE_DUE_SOON)->count())->toBe(1);
});

test('due reminders honour a custom window', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-08'],
    ]));

    $this->artisan('loans:send-due-reminders', ['--days' => 7])->assertSuccessful();

    Notification::assertSentTo($loan->borrower, LoanDueSoon::class);
});

test('due reminders are only sent once per installment', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-04'],
    ]));

    $this->artisan('loans:send-due-reminders')->assertSuccessful();
    $this->artisan('loans:send-due-reminders')->assertSuccessful();

    Notification::assertSentTimes(LoanDueSoon::class, 1);
});

test('due reminders skip settled installments', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-04'],
    ]));

    $loan->installments()->first()->update(['principal_due' => 0]);

    $this->artisan('loans:send-due-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

test('due reminders respect the borrower preference', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-04'],
    ]));
    $loan->borrower->update(['notify_due_soon' => false]);

    $this->artisan('loans:send-due-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect(NotificationLog::query()->count())->toBe(0);
});

test('due reminders are skipped when the borrower has no email', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-04'],
    ]);
    $loan->borrower->update(['email' => null]);

    $this->artisan('loans:send-due-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

test('the overdue command summarises every unpaid installment once a day', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
        ['principal' => 200, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-15'],
        ['principal' => 300, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]));

    $this->artisan('loans:send-overdue-notices')->assertSuccessful();
    $this->artisan('loans:send-overdue-notices')->assertSuccessful();

    Notification::assertSentTimes(LoanOverdue::class, 1);
    Notification::assertSentTo(
        $loan->borrower,
        LoanOverdue::class,
        fn (LoanOverdue $notification): bool => $notification->installments->count() === 2,
    );
});

test('overdue notices respect the borrower preference', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]));
    $loan->borrower->update(['notify_overdue' => false]);

    $this->artisan('loans:send-overdue-notices')->assertSuccessful();

    Notification::assertNothingSent();
});

test('staff can send overdue reminders for selected loans', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    $first = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]), 'first@example.com');

    $second = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-15'],
    ]), 'second@example.com');

    $this->actingAs($admin)
        ->post(route('admin.loans.remind'), [
            'loans' => [$first->id, $second->id],
        ])
        ->assertRedirect();

    Notification::assertSentTo($first->borrower, LoanOverdue::class);
    Notification::assertSentTo($second->borrower, LoanOverdue::class);

    expect(NotificationLog::query()->where('type', NotificationLog::TYPE_OVERDUE)->count())->toBe(2);
});

test('bulk reminders honour preferences, dedupe and overdue state', function () {
    Notification::fake();
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    $optedOut = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]), 'out@example.com');
    $optedOut->borrower->update(['notify_overdue' => false]);

    $alreadySent = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]), 'sent@example.com');

    NotificationLog::create([
        'key' => sprintf('loan:%d:overdue:%s', $alreadySent->id, today()->toDateString()),
        'type' => NotificationLog::TYPE_OVERDUE,
        'channel' => 'mail',
        'borrower_id' => $alreadySent->borrower_id,
        'loan_id' => $alreadySent->id,
        'sent_at' => now(),
    ]);

    $eligible = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]), 'eligible@example.com');

    $notOverdue = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]), 'future@example.com');

    $this->actingAs($admin)
        ->post(route('admin.loans.remind'), [
            'loans' => [$optedOut->id, $alreadySent->id, $eligible->id, $notOverdue->id],
        ])
        ->assertRedirect();

    Notification::assertNotSentTo($optedOut->borrower, LoanOverdue::class);
    Notification::assertNotSentTo($alreadySent->borrower, LoanOverdue::class);
    Notification::assertNotSentTo($notOverdue->borrower, LoanOverdue::class);
    Notification::assertSentTo($eligible->borrower, LoanOverdue::class);
    Notification::assertSentTimes(LoanOverdue::class, 1);

    expect(NotificationLog::query()->where('type', NotificationLog::TYPE_OVERDUE)->count())->toBe(2);
});

test('regular users cannot send bulk reminders', function () {
    Notification::fake();

    $user = User::factory()->create();
    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]));

    $this->actingAs($user)
        ->post(route('admin.loans.remind'), ['loans' => [$loan->id]])
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('bulk reminders require at least one loan', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.remind'), ['loans' => []])
        ->assertSessionHasErrors('loans');
});

test('recording a payment emails the borrower a receipt', function () {
    Notification::fake();

    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]));

    $actor = User::factory()->admin()->create();

    app(RepaymentService::class)->recordPayment($loan, $actor, 50, PaymentMethod::Cash);

    Notification::assertSentTo(
        $loan->borrower,
        PaymentReceived::class,
        fn (PaymentReceived $notification): bool => (float) $notification->payment->amount === 50.0
            && $notification->remainingOutstanding === 70.0,
    );
});

test('receipts respect the borrower preference', function () {
    Notification::fake();

    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]));
    $loan->borrower->update(['notify_receipts' => false]);

    $actor = User::factory()->admin()->create();

    app(RepaymentService::class)->recordPayment($loan, $actor, 10, PaymentMethod::Cash);

    Notification::assertNothingSent();
});

test('staff can email a statement to the borrower', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 10, 'fee' => 5, 'due_date' => '2026-07-01'],
    ]));

    $this->actingAs($admin)
        ->post(route('admin.loans.statement', $loan))
        ->assertRedirect(route('admin.loans.show', $loan));

    Notification::assertSentTo($loan->borrower, LoanStatement::class);

    expect(NotificationLog::query()->where('type', NotificationLog::TYPE_STATEMENT)->count())->toBe(1);
});

test('the statement is not sent without an email address', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);
    $loan->borrower->update(['email' => null]);

    $this->actingAs($admin)
        ->post(route('admin.loans.statement', $loan))
        ->assertRedirect(route('admin.loans.show', $loan));

    Notification::assertNothingSent();
});

test('regular users cannot email statements', function () {
    Notification::fake();

    $user = User::factory()->create();
    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]));

    $this->actingAs($user)
        ->post(route('admin.loans.statement', $loan))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('the loan page offers the statement only when an email exists', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.statement', true));

    $loan->borrower->update(['email' => null]);

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.statement', false));
});

test('borrower notification preferences persist through the form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.borrowers.store'), [
            'name' => 'Preference Tester',
            'phone' => '+1-555-0100',
            'email' => 'prefs@example.com',
            'kyc_status' => 'pending',
            'notify_due_soon' => '1',
            'notify_overdue' => '0',
            'notify_receipts' => '0',
            'notify_statements' => '1',
        ])
        ->assertRedirect(route('admin.borrowers.index'));

    $borrower = Borrower::query()->where('email', 'prefs@example.com')->firstOrFail();

    expect($borrower->notify_due_soon)->toBeTrue()
        ->and($borrower->notify_overdue)->toBeFalse()
        ->and($borrower->notify_receipts)->toBeFalse()
        ->and($borrower->notify_statements)->toBeTrue();
});

test('the statement mail renders the repayment schedule', function () {
    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 10, 'fee' => 5, 'due_date' => '2026-07-01'],
    ]));

    $mail = (new LoanStatement($loan))->toMail($loan->borrower);

    expect($mail->markdown)->toBe('mail.loan-statement')
        ->and($mail->viewData['rows'])->toHaveCount(1)
        ->and($mail->viewData['rows'][0]['outstanding'])->toBe('115.00')
        ->and($mail->viewData['totals']['outstanding'])->toBe('115.00');

    $html = (string) app(Markdown::class)
        ->render('mail.loan-statement', $mail->viewData);

    expect($html)->toContain('Loan statement')
        ->toContain($loan->borrower->name)
        ->toContain('115.00');
});

test('staff can preview the statement email without sending it', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 10, 'fee' => 5, 'due_date' => '2026-07-01'],
    ]));

    $response = $this->actingAs($admin)
        ->getJson(route('admin.loans.statement.preview', $loan))
        ->assertOk()
        ->assertJson([
            'eligible' => true,
            'to' => $loan->borrower->email,
            'subject' => "Statement for loan #{$loan->id}",
        ]);

    expect($response->json('html'))
        ->toContain('Loan statement')
        ->toContain($loan->borrower->name)
        ->toContain('115.00');

    Notification::assertNothingSent();
});

test('the statement preview explains when the borrower cannot receive statements', function () {
    $admin = User::factory()->admin()->create();
    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]));

    $loan->borrower->update(['notify_statements' => false]);

    $this->actingAs($admin)
        ->getJson(route('admin.loans.statement.preview', $loan))
        ->assertOk()
        ->assertJson([
            'eligible' => false,
            'to' => $loan->borrower->email,
        ]);

    $loan->borrower->update(['email' => null, 'notify_statements' => true]);

    $this->actingAs($admin)
        ->getJson(route('admin.loans.statement.preview', $loan))
        ->assertOk()
        ->assertJson([
            'eligible' => false,
            'to' => null,
        ]);
});

test('regular users cannot preview statements', function () {
    $user = User::factory()->create();
    $loan = withBorrowerEmail(activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]));

    $this->actingAs($user)
        ->getJson(route('admin.loans.statement.preview', $loan))
        ->assertForbidden();
});
