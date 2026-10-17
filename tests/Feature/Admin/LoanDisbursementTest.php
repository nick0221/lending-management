<?php

use App\Enums\DisbursementMethod;
use App\Enums\InterestMethod;
use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\User;
use App\Services\LoanService;

/**
 * Create a fully approved loan (with a schedule and approval event) using
 * deterministic terms for the disbursement tests.
 */
function approvedLoan(array $attributes = []): Loan
{
    $loan = Loan::factory()->pending()->create([
        'principal' => 12000,
        'interest_rate' => 12,
        'interest_method' => InterestMethod::Flat->value,
        'repayment_frequency' => RepaymentFrequency::Monthly->value,
        'processing_fee_rate' => 0,
        'term_months' => 12,
        ...$attributes,
    ]);

    return app(LoanService::class)->approve($loan);
}

test('admins can disburse an approved loan', function () {
    $admin = User::factory()->admin()->create();
    $loan = approvedLoan();

    $this->actingAs($admin)
        ->post(route('admin.loans.disburse', $loan), [
            'amount' => 12000,
            'method' => DisbursementMethod::BankTransfer->value,
            'reference' => 'TRX-123',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.loans.show', $loan));

    $loan->refresh()->load('disbursements');

    expect($loan->status)->toBe(LoanStatus::Active)
        ->and($loan->disbursed_at)->not->toBeNull()
        ->and($loan->disbursements)->toHaveCount(1);

    $disbursement = $loan->disbursements->first();

    expect($disbursement->amount)->toBe('12000.00')
        ->and($disbursement->method)->toBe(DisbursementMethod::BankTransfer)
        ->and($disbursement->reference)->toBe('TRX-123')
        ->and($disbursement->recorded_by)->toBe($admin->id);
});

test('a disbursed loan cannot be disbursed again', function () {
    $admin = User::factory()->admin()->create();
    $loan = approvedLoan();

    $this->actingAs($admin)->post(route('admin.loans.disburse', $loan), [
        'amount' => 12000,
        'method' => DisbursementMethod::Cash->value,
    ]);

    // The loan is now Active, so the disburse policy blocks the second attempt.
    $this->actingAs($admin)
        ->post(route('admin.loans.disburse', $loan), [
            'amount' => 12000,
            'method' => DisbursementMethod::Cash->value,
        ])
        ->assertForbidden();

    expect(Disbursement::where('loan_id', $loan->id)->count())->toBe(1);
});

test('a pending loan cannot be disbursed', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->pending()->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.disburse', $loan), [
            'amount' => 10000,
            'method' => DisbursementMethod::Cash->value,
        ])
        ->assertForbidden();

    expect($loan->fresh()->status)->toBe(LoanStatus::Pending);
});

test('the disbursed amount may not exceed the approved principal', function () {
    $admin = User::factory()->admin()->create();
    $loan = approvedLoan();

    $this->actingAs($admin)
        ->post(route('admin.loans.disburse', $loan), [
            'amount' => 13000,
            'method' => DisbursementMethod::Cash->value,
        ])
        ->assertSessionHasErrors('amount');

    expect($loan->fresh()->status)->toBe(LoanStatus::Approved);
});

test('disbursing rebuilds the schedule around the disbursed amount and date', function () {
    $admin = User::factory()->admin()->create();
    $loan = approvedLoan();

    // Approving built a 12000 schedule.
    expect($loan->installments()->count())->toBe(12)
        ->and(round($loan->installments()->sum('principal_due'), 2))->toEqualWithDelta(12000, 0.01);

    $disbursedAt = now()->addDays(5)->startOfDay();

    $this->actingAs($admin)->post(route('admin.loans.disburse', $loan), [
        'amount' => 6000,
        'method' => DisbursementMethod::Cash->value,
        'disbursed_at' => $disbursedAt->toDateString(),
    ])->assertSessionHasNoErrors();

    $loan->refresh()->load('installments');

    // The schedule now reconciles to the 6000 actually paid out.
    expect($loan->installments)->toHaveCount(12)
        ->and(round($loan->installments->sum('principal_due'), 2))->toEqualWithDelta(6000, 0.01)
        ->and($loan->installments->first()->due_date->toDateString())
        ->toBe($disbursedAt->copy()->addMonth()->toDateString())
        ->and($loan->disbursed_at->toDateString())->toBe($disbursedAt->toDateString());
});

test('the disbursement writes an audit trail of who did what and when', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $loan = approvedLoan();

    $this->actingAs($admin)->post(route('admin.loans.disburse', $loan), [
        'amount' => 12000,
        'method' => DisbursementMethod::BankTransfer->value,
    ])->assertSessionHasNoErrors();

    $events = LoanEvent::where('loan_id', $loan->id)->orderBy('id')->get();

    expect($events->pluck('action')->all())->toBe([
        LoanEventType::Approved,
        LoanEventType::Disbursed,
        LoanEventType::Activated,
    ])
        ->and($events->last()->from_status)->toBe(LoanStatus::Disbursed)
        ->and($events->last()->to_status)->toBe(LoanStatus::Active)
        ->and($events->last()->user_id)->toBe($admin->id);
});

test('the loan detail exposes the disbursement, audit trail and abilities', function () {
    $admin = User::factory()->admin()->create();
    $loan = approvedLoan();

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/show')
            ->where('can.disburse', true)
            ->where('can.delete', true)
            ->has('disbursements', 0)
        );

    $this->actingAs($admin)->post(route('admin.loans.disburse', $loan), [
        'amount' => 12000,
        'method' => DisbursementMethod::BankTransfer->value,
        'reference' => 'TRX-9',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.disburse', false)
            ->where('can.delete', false)
            ->has('disbursements', 1)
            ->where('disbursements.0.amount', '12000.00')
            ->where('disbursements.0.method', 'bank_transfer')
            ->where('disbursements.0.recorded_by', $admin->name)
            ->has('events')
        );
});
