<?php

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
