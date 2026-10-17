<?php

use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\User;
use App\Services\RepaymentService;

test('admins can flag an active loan as defaulted', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.loans.mark-default', $loan))
        ->assertRedirect(route('admin.loans.show', $loan));

    expect($loan->fresh()->status)->toBe(LoanStatus::Defaulted);
});

test('admins can write off a loan', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.loans.write-off', $loan))
        ->assertRedirect(route('admin.loans.show', $loan));

    expect($loan->fresh()->status)->toBe(LoanStatus::WrittenOff);
});

test('defaulted loans can be written off', function () {
    $admin = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Defaulted)->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.write-off', $loan))
        ->assertRedirect(route('admin.loans.show', $loan));

    expect($loan->fresh()->status)->toBe(LoanStatus::WrittenOff);
});

test('restructuring reschedules the outstanding principal', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-01'],
    ]);

    $loan->forceFill([
        'interest_rate' => 0,
        'processing_fee_rate' => 0,
        'term_months' => 2,
    ])->save();

    $loan->loanProduct->update(['min_term_months' => 1, 'max_term_months' => 60]);

    $this->actingAs($admin)
        ->post(route('admin.loans.restructure', $loan), [
            'term_months' => 2,
            'starts_on' => '2026-06-15',
        ])
        ->assertRedirect(route('admin.loans.show', $loan));

    $loan->refresh();
    $installments = $loan->installments()->orderBy('sequence')->get();

    expect($loan->term_months)->toBe(2)
        ->and($installments)->toHaveCount(2)
        ->and((float) $installments->sum('principal_due'))->toBe(200.0)
        ->and((float) $installments->first()->principal_due)->toBe(100.0)
        ->and($installments->first()->due_date->toDateString())->toBe('2026-07-15')
        ->and($loan->status)->toBe(LoanStatus::Active)
        ->and($loan->events()->where('action', LoanEventType::Restructured->value)->count())->toBe(1);
});

test('restructuring only reschedules the principal still owed', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-01'],
    ]);

    $loan->forceFill([
        'interest_rate' => 0,
        'processing_fee_rate' => 0,
        'term_months' => 2,
    ])->save();

    $loan->loanProduct->update(['min_term_months' => 1, 'max_term_months' => 60]);

    $firstInstallment = $loan->installments()->orderBy('sequence')->first();
    $firstInstallment->forceFill(['principal_due' => 100])->save();

    // Settle the first installment's principal in full.
    $payment = $loan->payments()->create([
        'amount' => 100,
        'method' => 'cash',
        'payment_date' => now(),
        'recorded_by' => $admin->id,
    ]);
    $payment->allocations()->create([
        'loan_id' => $loan->id,
        'installment_id' => $firstInstallment->id,
        'sequence' => $firstInstallment->sequence,
        'principal_allocated' => 100,
        'interest_allocated' => 0,
        'fee_allocated' => 0,
        'penalty_allocated' => 0,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.loans.restructure', $loan), ['term_months' => 2])
        ->assertRedirect(route('admin.loans.show', $loan));

    expect((float) $loan->refresh()->installments()->sum('principal_due'))->toBe(100.0);
});

test('restructuring preserves the payment history and the paid total', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 200, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-01'],
    ]);

    $loan->forceFill([
        'interest_rate' => 0,
        'processing_fee_rate' => 0,
        'term_months' => 2,
    ])->save();

    $loan->loanProduct->update(['min_term_months' => 1, 'max_term_months' => 60]);

    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 100, PaymentMethod::Cash);

    $this->actingAs($admin)
        ->post(route('admin.loans.restructure', $loan), ['term_months' => 2])
        ->assertRedirect(route('admin.loans.show', $loan));

    $allocation = $payment->refresh()->allocations()->first();

    expect($allocation)->not->toBeNull()
        ->and($allocation->sequence)->toBe(1)
        ->and($allocation->installment_id)->toBeNull()
        ->and($allocation->principal_allocated)->toBe('100.00');

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/show')
            ->where('totals.paid', '100.00')
            ->where('totals.outstanding', '200.00')
            ->where('payments.0.allocations.0.installment_sequence', 1)
        );
});

test('restructuring reactivates a defaulted loan', function () {
    $admin = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Defaulted)->create([
        'interest_rate' => 0,
        'processing_fee_rate' => 0,
        'term_months' => 2,
    ]);

    $loan->installments()->create([
        'sequence' => 1,
        'due_date' => '2026-05-01',
        'principal_due' => 100,
        'interest_due' => 0,
        'fee_due' => 0,
        'balance' => 0,
    ]);

    $loan->loanProduct->update(['min_term_months' => 1, 'max_term_months' => 60]);

    $this->actingAs($admin)
        ->post(route('admin.loans.restructure', $loan), ['term_months' => 2])
        ->assertRedirect(route('admin.loans.show', $loan));

    expect($loan->fresh()->status)->toBe(LoanStatus::Active);
});

test('restructuring validates the term against the product limits', function () {
    $admin = User::factory()->admin()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);

    $loan->loanProduct->update(['min_term_months' => 3, 'max_term_months' => 12]);

    $this->actingAs($admin)
        ->post(route('admin.loans.restructure', $loan), ['term_months' => 24])
        ->assertSessionHasErrors('term_months');

    expect($loan->fresh()->installments()->count())->toBe(1);
});

test('regular users cannot manage loan arrears', function () {
    $user = User::factory()->create();

    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.loans.mark-default', $loan))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.loans.write-off', $loan))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.loans.restructure', $loan), ['term_months' => 12])
        ->assertForbidden();

    expect($loan->fresh()->status)->toBe(LoanStatus::Active);
});
