<?php

use App\Enums\DisbursementMethod;
use App\Enums\InterestMethod;
use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\RepaymentFrequency;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Services\LoanService;
use App\Services\RepaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

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

test('the payments tab can be filtered by method and date', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();
    $service = app(RepaymentService::class);

    $service->recordPayment($loan, $admin, 112, PaymentMethod::Cash, null, null, Carbon::parse('2026-01-10'));
    $service->recordPayment($loan->fresh(), $admin, 112, PaymentMethod::BankTransfer, null, null, Carbon::parse('2026-02-10'));

    $this->actingAs($admin)
        ->get(route('admin.loans.show', [
            'loan' => $loan,
            'tab' => 'payments',
            'payment_method' => PaymentMethod::Cash->value,
            'payment_from' => '2026-01-01',
            'payment_to' => '2026-01-31',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/show')
            ->has('payments', 1)
            ->where('payments.0.method', 'cash')
            ->where('payments.0.amount', '112.00')
            ->where('paymentCount', 2)
            ->where('paymentFilters.method', 'cash')
            ->where('paymentFilters.from', '2026-01-01')
            ->where('paymentFilters.to', '2026-01-31')
        );
});

test('a payment receipt shows the payment and its allocation', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();
    $payment = app(RepaymentService::class)->recordPayment(
        $loan,
        $admin,
        112,
        PaymentMethod::BankTransfer,
        'RCPT-1',
        'January payment',
    );

    $this->actingAs($admin)
        ->get(route('admin.loans.payments.receipt', [
            'loan' => $loan,
            'payment' => $payment,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/receipt')
            ->where('organisation', config('app.name'))
            ->where('receipt.number', 'R-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT))
            ->where('receipt.amount', '112.00')
            ->where('receipt.method_label', 'Bank Transfer')
            ->where('receipt.reference', 'RCPT-1')
            ->where('receipt.notes', 'January payment')
            ->where('receipt.recorded_by', $admin->name)
            ->where('receipt.borrower_name', $loan->borrower->name)
            ->where('receipt.loan_id', $loan->id)
            ->has('receipt.allocations', 1)
            ->where('receipt.allocations.0.principal_allocated', '100.00')
            ->where('receipt.allocations.0.interest_allocated', '12.00')
        );
});

test('a payment receipt cannot be opened through another loan', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();
    $otherLoan = disbursedActiveLoan();
    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 112, PaymentMethod::Cash);

    $this->actingAs($admin)
        ->get(route('admin.loans.payments.receipt', [
            'loan' => $otherLoan,
            'payment' => $payment,
        ]))
        ->assertNotFound();
});

test('staff can re-send a payment receipt', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();
    $payment = app(RepaymentService::class)->recordPayment($loan, $admin, 112, PaymentMethod::Cash);

    expect(NotificationLog::query()->where('type', NotificationLog::TYPE_RECEIPT)->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.receipt.email', [
            'loan' => $loan,
            'payment' => $payment,
        ]))
        ->assertRedirect();

    expect(NotificationLog::query()->where('type', NotificationLog::TYPE_RECEIPT)->count())->toBe(2);

    Notification::assertSentTo($loan->borrower, PaymentReceived::class);
});

test('re-sending a receipt respects the borrower opt-out', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = disbursedActiveLoan();
    $loan->borrower->update(['notify_receipts' => false]);
    $payment = app(RepaymentService::class)->recordPayment($loan->fresh(), $admin, 112, PaymentMethod::Cash);

    expect(NotificationLog::query()->where('type', NotificationLog::TYPE_RECEIPT)->count())->toBe(0);

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.receipt.email', [
            'loan' => $loan,
            'payment' => $payment,
        ]))
        ->assertRedirect();

    expect(NotificationLog::query()->where('type', NotificationLog::TYPE_RECEIPT)->count())->toBe(0);
});
