<?php

use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\LoanEvent;
use App\Models\User;
use App\Services\PenaltyService;
use App\Services\RepaymentService;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

test('a monthly penalty accrues once a full period is overdue', function () {
    $this->travelTo('2026-06-01');

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5, 'due_date' => '2026-05-01'],
    ], penaltyRate: 5);

    $result = app(PenaltyService::class)->accrueForLoan($loan, Date::parse('2026-06-01'));

    expect($result['amount'])->toBe(6.25)
        ->and($result['installments'])->toBe(1);

    $installment = $loan->installments()->first();

    expect($installment->penalty_accrued)->toBe('6.25')
        ->and($installment->penalty_accrued_periods)->toBe(1);
});

test('no penalty accrues before a full period is overdue', function () {
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5, 'due_date' => '2026-05-01'],
    ], penaltyRate: 5);

    $result = app(PenaltyService::class)->accrueForLoan($loan, Date::parse('2026-05-20'));

    expect($result['amount'])->toBe(0.0)
        ->and($loan->installments()->first()->penalty_accrued)->toBe('0.00');
});

test('the grace period delays the start of penalty accrual', function () {
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ], penaltyRate: 10, gracePeriodDays: 10);

    $service = app(PenaltyService::class);

    expect($service->accrueForLoan($loan, Date::parse('2026-06-09'))['amount'])->toBe(0.0)
        ->and($service->accrueForLoan($loan, Date::parse('2026-06-10'))['amount'])->toBe(10.0);
});

test('accrual is idempotent for the same day', function () {
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ], penaltyRate: 10);

    $service = app(PenaltyService::class);

    $service->accrueForLoan($loan, Date::parse('2026-06-01'));
    $second = $service->accrueForLoan($loan, Date::parse('2026-06-01'));

    expect($second['amount'])->toBe(0.0)
        ->and($loan->installments()->first()->penalty_accrued)->toBe('10.00');
});

test('each additional full period accrues another penalty', function () {
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ], penaltyRate: 10);

    $service = app(PenaltyService::class);

    $service->accrueForLoan($loan, Date::parse('2026-06-01'));
    $second = $service->accrueForLoan($loan, Date::parse('2026-07-01'));

    expect($second['amount'])->toBe(10.0)
        ->and($loan->installments()->first()->penalty_accrued)->toBe('20.00')
        ->and($loan->installments()->first()->penalty_accrued_periods)->toBe(2);
});

test('the accrual command records a penalty event', function () {
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ], penaltyRate: 10);

    $this->artisan('loans:accrue-penalties', ['--date' => '2026-06-01'])->assertSuccessful();

    expect($loan->installments()->first()->penalty_accrued)->toBe('10.00')
        ->and(LoanEvent::query()
            ->where('loan_id', $loan->id)
            ->where('action', LoanEventType::PenaltyAccrued->value)
            ->count())->toBe(1);
});

test('the accrual command skips products without a penalty rate', function () {
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);

    $this->artisan('loans:accrue-penalties', ['--date' => '2026-06-01'])->assertSuccessful();

    expect($loan->installments()->first()->penalty_accrued)->toBe('0.00');
});

test('payments settle accrued penalties before scheduled dues', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ], penaltyRate: 10);

    $loan->installments()->first()->forceFill([
        'penalty_accrued' => 10,
        'penalty_accrued_periods' => 1,
    ])->save();

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 10, PaymentMethod::Cash);
    $allocation = $payment->allocations()->first();

    expect($allocation->penalty_allocated)->toBe('10.00')
        ->and($allocation->principal_allocated)->toBe('0.00');
});

test('a payment may not exceed dues plus penalties', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ], penaltyRate: 10);

    $loan->installments()->first()->forceFill(['penalty_accrued' => 10])->save();

    expect(fn () => app(RepaymentService::class)->recordPayment($loan, $admin, 111, PaymentMethod::Cash))
        ->toThrow(ValidationException::class);

    expect($loan->payments()->count())->toBe(0);
});

test('settling dues and penalties closes the loan', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ], penaltyRate: 10);

    $loan->installments()->first()->forceFill(['penalty_accrued' => 10])->save();

    app(RepaymentService::class)->recordPayment($loan, $admin, 110, PaymentMethod::Cash);

    expect($loan->fresh()->status)->toBe(LoanStatus::Closed);
});
