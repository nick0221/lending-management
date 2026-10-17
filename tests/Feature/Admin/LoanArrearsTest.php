<?php

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use App\Services\PenaltyService;
use Illuminate\Support\Facades\Date;

test('the overdue command refreshes the days past due flag', function () {
    $this->travelTo('2026-06-01');

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);

    $this->artisan('loans:flag-overdue')->assertSuccessful();

    $loan->refresh();

    expect($loan->days_past_due)->toBe(31)
        ->and($loan->status)->toBe(LoanStatus::Active);
});

test('the overdue command defaults loans past the threshold', function () {
    $this->travelTo('2026-06-01');

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-02-01'],
    ]);

    $this->artisan('loans:flag-overdue', ['--days' => 90])->assertSuccessful();

    $loan->refresh();

    expect($loan->days_past_due)->toBe(120)
        ->and($loan->status)->toBe(LoanStatus::Defaulted);
});

test('the overdue command leaves on-track loans alone', function () {
    $this->travelTo('2026-06-01');

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    $this->artisan('loans:flag-overdue')->assertSuccessful();

    expect($loan->fresh()->days_past_due)->toBe(0);
});

test('the loans index can filter to overdue loans', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    $overdue = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);
    $overdue->forceFill(['days_past_due' => 31])->save();

    activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.loans.index', ['overdue' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/index')
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $overdue->id)
            ->where('loans.data.0.days_past_due', 31)
            ->where('loans.data.0.arrears', '100.00')
            ->where('overdueOnly', true)
        );
});

test('the loan detail exposes overdue installments, penalties and aging', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ], penaltyRate: 10);

    app(PenaltyService::class)->accrueForLoan($loan, Date::parse('2026-06-01'));

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/show')
            ->where('installments.0.overdue', true)
            ->where('installments.0.days_past_due', 31)
            ->where('installments.0.penalty_accrued', '10.00')
            ->where('installments.0.outstanding', '110.00')
            ->where('installments.1.overdue', false)
            ->where('totals.penalty', '10.00')
            ->where('totals.arrears', '110.00')
            ->where('totals.outstanding', '210.00')
            ->where('totals.aging.current', '100.00')
            ->where('totals.aging.days_1_30', '0.00')
            ->where('totals.aging.days_31_60', '110.00')
            ->where('can.default', true)
            ->where('can.write_off', true)
            ->where('can.restructure', true)
        );
});

test('the loan list exposes the arrears figure for overdue loans', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 250, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);
    $loan->forceFill(['days_past_due' => 31])->save();

    $this->actingAs($admin)
        ->get(route('admin.loans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/index')
            ->has('loans.data', 1)
            ->where('loans.data.0.arrears', '250.00')
        );
});

test('closed loans do not carry a days past due flag', function () {
    $this->travelTo('2026-06-01');

    $loan = Loan::factory()->withStatus(LoanStatus::Closed)->create(['days_past_due' => 45]);

    $this->artisan('loans:flag-overdue')->assertSuccessful();

    expect($loan->fresh()->days_past_due)->toBe(0);
});
