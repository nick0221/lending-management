<?php

use App\Enums\DisbursementMethod;
use App\Enums\InterestMethod;
use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\RepaymentFrequency;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\User;
use App\Services\LoanService;

/**
 * Originate, approve and disburse a deterministic 1200 loan so the repayment
 * totals are known: 100 principal + 12 flat interest per month, 1344 in total.
 */
function disbursedActiveLoan(): Loan
{
    $admin = User::factory()->admin()->create();

    $loan = Loan::factory()->pending()->create([
        'principal' => 1200,
        'interest_rate' => 12,
        'interest_method' => InterestMethod::Flat->value,
        'repayment_frequency' => RepaymentFrequency::Monthly->value,
        'processing_fee_rate' => 0,
        'term_months' => 12,
    ]);

    $service = app(LoanService::class);

    return $service->disburse(
        $service->approve($loan),
        $admin,
        1200,
        DisbursementMethod::BankTransfer,
    );
}

test('admins can record a payment against an active loan', function () {
    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.store', $loan), [
            'amount' => 112,
            'method' => PaymentMethod::BankTransfer->value,
            'reference' => 'PAY-1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.loans.show', $loan));

    $loan->refresh()->load('payments');

    expect($loan->payments)->toHaveCount(1)
        ->and($loan->payments->first()->amount)->toBe('112.00')
        ->and($loan->payments->first()->method)->toBe(PaymentMethod::BankTransfer)
        ->and($loan->payments->first()->reference)->toBe('PAY-1')
        ->and($loan->payments->first()->recorded_by)->toBe($admin->id)
        ->and($loan->status)->toBe(LoanStatus::Active);
});

test('a payment cannot be recorded against a loan that is not active', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->pending()->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.store', $loan), [
            'amount' => 100,
            'method' => PaymentMethod::Cash->value,
        ])
        ->assertForbidden();

    expect($loan->payments()->count())->toBe(0);
});

test('a payment greater than the outstanding balance is rejected', function () {
    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.store', $loan), [
            'amount' => 5000,
            'method' => PaymentMethod::Cash->value,
        ])
        ->assertSessionHasErrors('amount');

    expect($loan->payments()->count())->toBe(0)
        ->and($loan->fresh()->status)->toBe(LoanStatus::Active);
});

test('paying the full balance closes the loan automatically', function () {
    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.store', $loan), [
            'amount' => 1344,
            'method' => PaymentMethod::BankTransfer->value,
        ])
        ->assertSessionHasNoErrors();

    $loan->refresh();

    expect($loan->status)->toBe(LoanStatus::Closed)
        ->and($loan->closed_at)->not->toBeNull();
});

test('recording a payment writes an audit entry', function () {
    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();

    $this->actingAs($admin)->post(route('admin.loans.payments.store', $loan), [
        'amount' => 112,
        'method' => PaymentMethod::Cash->value,
    ])->assertSessionHasNoErrors();

    $paymentEvent = LoanEvent::where('loan_id', $loan->id)
        ->where('action', LoanEventType::PaymentRecorded->value)
        ->first();

    expect($paymentEvent)->not->toBeNull()
        ->and($paymentEvent->user_id)->toBe($admin->id)
        ->and($paymentEvent->to_status)->toBe(LoanStatus::Active);
});

test('the loan detail exposes payments, totals and per-installment progress', function () {
    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();

    $this->actingAs($admin)->post(route('admin.loans.payments.store', $loan), [
        'amount' => 112,
        'method' => PaymentMethod::BankTransfer->value,
        'reference' => 'PAY-9',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/show')
            ->where('can.pay', true)
            ->has('payments', 1)
            ->where('payments.0.amount', '112.00')
            ->where('payments.0.reference', 'PAY-9')
            ->where('payments.0.method', 'bank_transfer')
            ->where('payments.0.recorded_by', $admin->name)
            ->where('totals.payable', '1344.00')
            ->where('totals.paid', '112.00')
            ->where('totals.outstanding', '1232.00')
            ->where('installments.0.paid', '112.00')
            ->where('installments.0.outstanding', '0.00')
            ->where('installments.1.outstanding', '112.00')
        );

    // A closed loan can no longer accept payments.
    $this->actingAs($admin)->post(route('admin.loans.payments.store', $loan), [
        'amount' => 1232,
        'method' => PaymentMethod::Cash->value,
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan->fresh()))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.pay', false)
            ->where('totals.outstanding', '0.00')
        );
});
