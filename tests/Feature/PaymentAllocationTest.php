<?php

use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\User;
use App\Services\RepaymentService;
use Illuminate\Validation\ValidationException;

/**
 * Build an active loan with a hand-written schedule so the allocation math can
 * be asserted against exact figures.
 *
 * @param  array<int, array{principal: float|int, interest: float|int, fee: float|int}>  $installments
 */
function activeLoanWithSchedule(array $installments): Loan
{
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create([
        'principal' => array_sum(array_column($installments, 'principal')),
    ]);

    foreach ($installments as $index => $row) {
        $loan->installments()->create([
            'sequence' => $index + 1,
            'due_date' => now()->addMonths($index + 1),
            'principal_due' => $row['principal'],
            'interest_due' => $row['interest'],
            'fee_due' => $row['fee'],
            'balance' => 0,
        ]);
    }

    return $loan;
}

test('a payment is allocated oldest-first, fee then interest then principal', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
        ['principal' => 100, 'interest' => 10, 'fee' => 0],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 125, PaymentMethod::Cash);

    $allocations = $payment->allocations()->with('installment')->get();

    expect($allocations)->toHaveCount(1)
        ->and($allocations->first()->installment->sequence)->toBe(1)
        ->and($allocations->first()->fee_allocated)->toBe('5.00')
        ->and($allocations->first()->interest_allocated)->toBe('20.00')
        ->and($allocations->first()->principal_allocated)->toBe('100.00');
});

test('a partial payment consumes the due components in priority order', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 40, PaymentMethod::Cash);
    $allocation = $payment->allocations()->first();

    expect($allocation->fee_allocated)->toBe('5.00')
        ->and($allocation->interest_allocated)->toBe('20.00')
        ->and($allocation->principal_allocated)->toBe('15.00');
});

test('a payment larger than one installment spills into the next one', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
        ['principal' => 100, 'interest' => 10, 'fee' => 0],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 130, PaymentMethod::BankTransfer);
    $secondSequence = $loan->installments()->where('sequence', 2)->value('id');
    $second = $payment->allocations()->where('installment_id', $secondSequence)->first();

    expect($payment->allocations()->count())->toBe(2)
        ->and($second->fee_allocated)->toBe('0.00')
        ->and($second->interest_allocated)->toBe('5.00')
        ->and($second->principal_allocated)->toBe('0.00');
});

test('a payment beyond the outstanding balance is rejected', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
    ]);

    expect(fn () => app(RepaymentService::class)->recordPayment($loan, $admin, 200, PaymentMethod::Cash))
        ->toThrow(ValidationException::class);

    expect($loan->payments()->count())->toBe(0);
});

test('paying the full outstanding balance closes the loan', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
        ['principal' => 100, 'interest' => 10, 'fee' => 0],
    ]);

    app(RepaymentService::class)->recordPayment($loan, $admin, 235, PaymentMethod::BankTransfer);

    $loan->refresh();

    expect($loan->status)->toBe(LoanStatus::Closed)
        ->and($loan->closed_at)->not->toBeNull();
});

test('reversing a payment restores the installment balance and keeps the record', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 40, PaymentMethod::Cash);

    $reversed = app(RepaymentService::class)->reversePayment(
        $loan,
        $payment,
        $admin,
        'Recorded against the wrong loan.',
    );

    expect($reversed->reversed_at)->not->toBeNull()
        ->and($reversed->reversal_reason)->toBe('Recorded against the wrong loan.')
        ->and($reversed->reversed_by)->toBe($admin->id)
        ->and($reversed->allocations()->count())->toBe(0)
        ->and($loan->fresh()->status)->toBe(LoanStatus::Active)
        ->and($loan->installments()->first()->fresh()->outstandingTotal())->toBe(125.0);
});

test('reversing the payment that closed a loan reopens it', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 125, PaymentMethod::Cash);
    $loan->refresh();

    expect($loan->status)->toBe(LoanStatus::Closed);

    app(RepaymentService::class)->reversePayment($loan, $payment, $admin, 'The payment was duplicated.');

    $loan->refresh();
    $event = $loan->events()
        ->where('action', LoanEventType::PaymentReversed->value)
        ->first();

    expect($loan->status)->toBe(LoanStatus::Active)
        ->and($loan->closed_at)->toBeNull()
        ->and($event)->not->toBeNull()
        ->and($event->from_status)->toBe(LoanStatus::Closed)
        ->and($event->to_status)->toBe(LoanStatus::Active);
});

test('a payment cannot be reversed twice', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 40, PaymentMethod::Cash);
    $service = app(RepaymentService::class);

    $service->reversePayment($loan, $payment, $admin, 'First reversal.');

    expect(fn () => $service->reversePayment($loan, $payment->fresh(), $admin, 'Second reversal.'))
        ->toThrow(ValidationException::class);

    expect($payment->fresh()->reversal_reason)->toBe('First reversal.');
});

test('the reversal endpoint requires a reason and redirects to the payments tab', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 40, PaymentMethod::Cash);

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.reverse', [$loan, $payment]), [])
        ->assertSessionHasErrors('reason');

    expect($payment->fresh()->reversed_at)->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.reverse', [$loan, $payment]), [
            'reason' => 'Wrong amount.',
        ])
        ->assertRedirect(route('admin.loans.show', ['loan' => $loan, 'tab' => 'payments']));

    expect($payment->fresh()->reversed_at)->not->toBeNull();
});

test('regular users cannot reverse payments', function () {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 10, PaymentMethod::Cash);

    $this->actingAs($user)
        ->post(route('admin.loans.payments.reverse', [$loan, $payment]), [
            'reason' => 'Not allowed.',
        ])
        ->assertForbidden();
});

test('a payment cannot be reversed through another loan', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
    ]);
    $other = activeLoanWithSchedule([
        ['principal' => 50, 'interest' => 0, 'fee' => 0],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 10, PaymentMethod::Cash);

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.reverse', [$other, $payment]), [
            'reason' => 'Wrong loan.',
        ])
        ->assertNotFound();
});

test('reversed payments are marked and excluded from the paid total', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5],
    ]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 40, PaymentMethod::Cash);

    app(RepaymentService::class)->reversePayment($loan, $payment, $admin, 'Wrong amount.');

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totals.paid', '0.00')
            ->where('payments.0.can_reverse', false)
            ->where('payments.0.reversal_reason', 'Wrong amount.')
            ->where('payments.0.allocations', [])
        );
});
