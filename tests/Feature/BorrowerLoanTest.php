<?php

use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPaymentAllocation;
use App\Models\Payment;
use App\Models\User;

/**
 * A borrower account linked to a fresh borrower profile.
 *
 * @return array{0: User, 1: Borrower}
 */
function borrowerAccount(): array
{
    $user = User::factory()->create();
    $borrower = Borrower::factory()->create(['user_id' => $user->id]);

    return [$user, $borrower];
}

test('a borrower sees their own loan, schedule and payments', function () {
    [$user, $borrower] = borrowerAccount();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->for($borrower)->create([
        'principal' => 10000,
    ]);

    $first = LoanInstallment::factory()->for($loan)->create([
        'sequence' => 1,
        'due_date' => today()->subDays(10)->toDateString(),
        'principal_due' => 1000,
        'interest_due' => 100,
        'fee_due' => 0,
        'penalty_accrued' => 0,
    ]);

    LoanInstallment::factory()->for($loan)->create([
        'sequence' => 2,
        'due_date' => today()->addDays(20)->toDateString(),
        'principal_due' => 1000,
        'interest_due' => 100,
        'fee_due' => 0,
        'penalty_accrued' => 0,
    ]);

    $payment = Payment::factory()->for($loan)->create([
        'amount' => '500.00',
        'payment_date' => today(),
    ]);

    LoanPaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'loan_id' => $loan->id,
        'installment_id' => $first->id,
        'sequence' => 1,
        'principal_allocated' => '500.00',
        'interest_allocated' => 0,
        'fee_allocated' => 0,
        'penalty_allocated' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/show')
            ->where('loan.id', $loan->id)
            ->where('loan.borrower_name', $borrower->name)
            ->where('loan.status', 'active')
            ->has('installments', 2)
            ->where('installments.0.sequence', 1)
            ->where('installments.0.outstanding', '600.00')
            ->where('installments.0.overdue', true)
            ->where('installments.0.paid', '500.00')
            ->where('installments.1.outstanding', '1100.00')
            ->where('installments.1.overdue', false)
            ->has('payments', 1)
            ->where('payments.0.amount', '500.00')
            ->where('payments.0.reversed_at', null)
            ->where('payments.0.allocations.0.installment_sequence', 1)
            ->where('summary.payable', '2200.00')
            ->where('summary.paid', '500.00')
            ->where('summary.outstanding', '1700.00')
            ->where('summary.arrears', '600.00')
            ->where('summary.next_due.sequence', 1)
            ->where('summary.next_due.amount', '600.00')
            ->where('summary.next_due.overdue', true)
        );
});

test('a borrower cannot open another borrower loan', function () {
    [$user] = borrowerAccount();

    $otherBorrower = Borrower::factory()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->for($otherBorrower)->create();

    $this->actingAs($user)
        ->get(route('loans.show', $loan))
        ->assertForbidden();
});

test('a borrower account without a borrower profile cannot open loans', function () {
    $user = User::factory()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs($user)
        ->get(route('loans.show', $loan))
        ->assertForbidden();
});

test('staff cannot open the borrower loan page', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs($admin)
        ->get(route('loans.show', $loan))
        ->assertForbidden();
});

test('guests are sent to the login page from a loan', function () {
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->get(route('loans.show', $loan))
        ->assertRedirect(route('login'));
});

test('a borrower can print a statement for their own loan', function () {
    [$user, $borrower] = borrowerAccount();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->for($borrower)->create([
        'principal' => 10000,
    ]);

    LoanInstallment::factory()->for($loan)->create([
        'sequence' => 1,
        'due_date' => today()->subDays(5)->toDateString(),
        'principal_due' => 1000,
        'interest_due' => 100,
        'fee_due' => 0,
        'penalty_accrued' => 0,
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '400.00',
        'payment_date' => today(),
    ]);

    $this->actingAs($user)
        ->get(route('loans.statement', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/statement')
            ->where('loan.id', $loan->id)
            ->where('borrower.name', $borrower->name)
            ->has('installments', 1)
            ->where('installments.0.outstanding', '1100.00')
            ->has('payments', 1)
            ->where('payments.0.amount', '400.00')
            ->where('payments.0.can_reverse', false)
            ->where('totals.payable', '1100.00')
            ->where('totals.paid', '400.00')
            ->where('totals.outstanding', '1100.00')
            ->where('totals.arrears', '1100.00')
        );
});

test('a borrower cannot print another borrower statement', function () {
    [$user] = borrowerAccount();

    $otherBorrower = Borrower::factory()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->for($otherBorrower)->create();

    $this->actingAs($user)
        ->get(route('loans.statement', $loan))
        ->assertForbidden();
});

test('staff and unlinked accounts cannot print the borrower statement', function () {
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('loans.statement', $loan))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('loans.statement', $loan))
        ->assertForbidden();
});

test('a borrower can print a receipt for their own payment', function () {
    [$user, $borrower] = borrowerAccount();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->for($borrower)->create();

    $payment = Payment::factory()->for($loan)->create([
        'amount' => '400.00',
        'reference' => 'PAY-1',
        'payment_date' => today(),
    ]);

    $this->actingAs($user)
        ->get(route('loans.payments.receipt', [
            'loan' => $loan,
            'payment' => $payment,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/receipt')
            ->where('receipt.id', $payment->id)
            ->where('receipt.number', 'R-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT))
            ->where('receipt.amount', '400.00')
            ->where('receipt.reference', 'PAY-1')
            ->where('receipt.loan_id', $loan->id)
            ->where('receipt.borrower_name', $borrower->name)
            ->where('receipt.reversed_at', null)
            ->has('receipt.allocations')
        );
});

test('a borrower cannot print another borrower receipt', function () {
    [$user] = borrowerAccount();

    $otherBorrower = Borrower::factory()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->for($otherBorrower)->create();
    $payment = Payment::factory()->for($loan)->create();

    $this->actingAs($user)
        ->get(route('loans.payments.receipt', [
            'loan' => $loan,
            'payment' => $payment,
        ]))
        ->assertForbidden();
});

test('a receipt must belong to the loan in the URL', function () {
    [$user, $borrower] = borrowerAccount();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->for($borrower)->create();
    $otherLoan = Loan::factory()->withStatus(LoanStatus::Active)->for($borrower)->create();
    $payment = Payment::factory()->for($otherLoan)->create();

    $this->actingAs($user)
        ->get(route('loans.payments.receipt', [
            'loan' => $loan,
            'payment' => $payment,
        ]))
        ->assertNotFound();
});

test('staff and unlinked accounts cannot print the borrower receipt', function () {
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('loans.payments.receipt', [
            'loan' => $loan,
            'payment' => $payment,
        ]))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('loans.payments.receipt', [
            'loan' => $loan,
            'payment' => $payment,
        ]))
        ->assertForbidden();
});
