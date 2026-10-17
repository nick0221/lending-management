<?php

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use App\Models\Loan;
use App\Models\User;
use App\Services\ScheduleService;

test('approving a loan generates its installment schedule', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->pending()->create([
        'principal' => 12000,
        'interest_rate' => 12,
        'interest_method' => InterestMethod::Flat->value,
        'repayment_frequency' => RepaymentFrequency::Monthly->value,
        'processing_fee_rate' => 0,
        'term_months' => 12,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.loans.approve', $loan))
        ->assertSessionHasNoErrors();

    $loan->refresh()->load('installments');

    expect($loan->status)->toBe(LoanStatus::Approved)
        ->and($loan->installments)->toHaveCount(12)
        ->and(collect($loan->installments)->sum('principal_due'))->toEqualWithDelta(12000, 0.001)
        ->and($loan->installments->last()->balance)->toBe('0.00');
});

test('a pending loan has no schedule until it is approved', function () {
    $loan = Loan::factory()->pending()->create();

    expect($loan->installments()->count())->toBe(0);
});

test('installment due dates follow the repayment frequency', function () {
    $loan = Loan::factory()->pending()->create([
        'repayment_frequency' => RepaymentFrequency::Monthly->value,
        'term_months' => 3,
    ]);

    $startsOn = now()->startOfDay();

    app(ScheduleService::class)->generate($loan, $startsOn);

    $loan->load('installments');

    expect($loan->installments)->toHaveCount(3)
        ->and($loan->installments->first()->due_date->toDateString())
        ->toBe($startsOn->copy()->addMonth()->toDateString())
        ->and($loan->installments->last()->due_date->toDateString())
        ->toBe($startsOn->copy()->addMonths(3)->toDateString());
});

test('regenerating the schedule replaces the previous installments', function () {
    $loan = Loan::factory()->pending()->create(['term_months' => 6]);

    $service = app(ScheduleService::class);
    $service->generate($loan, now());
    $service->generate($loan, now()->addMonth());

    expect($loan->installments()->count())->toBe(6);
});

test('the loan detail exposes the repayment schedule', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->pending()->create([
        'principal' => 1200,
        'interest_rate' => 12,
        'interest_method' => InterestMethod::Flat->value,
        'repayment_frequency' => RepaymentFrequency::Monthly->value,
        'processing_fee_rate' => 0,
        'term_months' => 3,
    ]);

    app(ScheduleService::class)->generate($loan, now());

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/show')
            ->has('installments', 3)
            ->where('installments.0.sequence', 1)
            ->where('installments.2.balance', '0.00')
        );
});
