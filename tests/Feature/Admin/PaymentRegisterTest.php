<?php

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;

test('the payments register lists every payment newest first', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $loan->borrower->update(['name' => 'Ada Ledger', 'phone' => '+1-555-0100']);

    $older = Payment::factory()->for($loan)->create([
        'amount' => 500,
        'payment_date' => today()->subDays(5),
        'recorded_by' => $admin->id,
    ]);
    $newer = Payment::factory()->for($loan)->create([
        'amount' => 750,
        'payment_date' => today(),
        'method' => PaymentMethod::Cash->value,
        'recorded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/payments/index')
            ->has('payments.data', 2)
            ->where('payments.data.0.id', $newer->id)
            ->where('payments.data.0.borrower_name', 'Ada Ledger')
            ->where('payments.data.0.method_label', 'Cash')
            ->where('payments.data.1.id', $older->id)
            ->where('counts.all', 2)
            ->where('counts.completed', 2)
            ->where('counts.reversed', 0)
            ->where('summary.count', 2)
            ->where('summary.received', '1250.00')
        );
});

test('the register tabs separate completed and reversed payments', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    Payment::factory()->for($loan)->create(['amount' => 400]);
    Payment::factory()->for($loan)->create([
        'amount' => 600,
        'reversed_at' => now(),
        'reversed_by' => $admin->id,
        'reversal_reason' => 'Duplicate entry',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['status' => 'reversed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/payments/index')
            ->has('payments.data', 1)
            ->where('payments.data.0.amount', '600.00')
            ->where('payments.data.0.reversed', true)
            ->where('payments.data.0.reversal_reason', 'Duplicate entry')
            ->where('counts.all', 2)
            ->where('summary.received', '400.00')
            ->where('summary.reversed', 1)
        );
});

test('the register searches by borrower, national ID, reference and loan number', function () {
    $admin = User::factory()->admin()->create();

    $matchingLoan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $matchingLoan->borrower->update([
        'name' => 'Grace Reference',
        'national_id' => 'NID-GRACE-REF',
    ]);

    $matching = Payment::factory()->for($matchingLoan)->create([
        'reference' => 'PAY-GRACE-1',
    ]);
    Payment::factory()->create(['reference' => 'PAY-OTHER-9']);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['search' => 'Grace']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $matching->id)
        );

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['search' => 'NID-GRACE-REF']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $matching->id)
        );

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['search' => 'PAY-GRACE-1']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $matching->id)
        );

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['search' => '#'.$matchingLoan->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $matching->id)
        );
});

test('the register filters by method and payment date', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $cash = Payment::factory()->for($loan)->create([
        'method' => PaymentMethod::Cash->value,
        'payment_date' => today()->subDays(2),
    ]);
    Payment::factory()->for($loan)->create([
        'method' => PaymentMethod::BankTransfer->value,
        'payment_date' => today()->subDays(40),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', [
            'method' => PaymentMethod::Cash->value,
            'from' => today()->subWeek()->toDateString(),
            'to' => today()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $cash->id)
            ->where('currentMethod', PaymentMethod::Cash->value)
        );
});

test('the register filters by the staff member who recorded the payment', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $officer = User::factory()->create(['name' => 'Sam Officer']);
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    Payment::factory()->for($loan)->create([
        'amount' => 120,
        'recorded_by' => $admin->id,
    ]);
    $theirs = Payment::factory()->for($loan)->create([
        'amount' => 340,
        'recorded_by' => $officer->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['recorded_by' => $officer->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $theirs->id)
            ->where('currentRecorder', $officer->id)
            ->has('recorders', 2)
        );

    $response = $this->actingAs($admin)
        ->get(route('admin.payments.export', ['recorded_by' => $officer->id]));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Sam Officer')
        ->not->toContain('Ada Admin');
});

test('the register reports which payments can still be reversed', function () {
    $admin = User::factory()->admin()->create();

    $active = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $closed = Loan::factory()->withStatus(LoanStatus::Closed)->create();
    $defaulted = Loan::factory()->withStatus(LoanStatus::Defaulted)->create();

    $reversible = Payment::factory()->for($active)->create();
    $closedPayment = Payment::factory()->for($closed)->create();
    $alreadyReversed = Payment::factory()->for($active)->create([
        'reversed_at' => now(),
        'reversal_reason' => 'Duplicated.',
    ]);
    $blocked = Payment::factory()->for($defaulted)->create();

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('payments.data', fn ($rows): bool => collect($rows)
                ->firstWhere('id', $reversible->id)['can_reverse'] === true
                && collect($rows)->firstWhere('id', $closedPayment->id)['can_reverse'] === true
                && collect($rows)->firstWhere('id', $alreadyReversed->id)['can_reverse'] === false
                && collect($rows)->firstWhere('id', $blocked->id)['can_reverse'] === false)
        );
});

test('a reversal started from the register returns to the register', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.reverse', [$loan, $payment]), [
            'reason' => 'Recorded against the wrong loan.',
            'redirect' => 'register',
        ])
        ->assertRedirect(route('admin.payments.index'));

    $reversed = $payment->fresh();

    expect($reversed->reversed_at)->not->toBeNull()
        ->and($reversed->reversal_reason)->toBe('Recorded against the wrong loan.');
});

test('a reversal rejects an unknown return target', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.payments.reverse', [$loan, $payment]), [
            'reason' => 'Recorded against the wrong loan.',
            'redirect' => 'somewhere-else',
        ])
        ->assertSessionHasErrors('redirect');

    expect($payment->fresh()->reversed_at)->toBeNull();
});

test('the register export honours the active filters', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $loan->borrower->update(['name' => 'Ada Export']);

    $included = Payment::factory()->for($loan)->create([
        'amount' => 111,
        'method' => PaymentMethod::MobileMoney->value,
        'reference' => 'MM-EXPORT-1',
        'payment_date' => today()->subDays(2),
    ]);
    Payment::factory()->for($loan)->create([
        'amount' => 222,
        'method' => PaymentMethod::Cash->value,
        'reference' => 'CASH-EXPORT-2',
        'payment_date' => today()->subDays(2),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.payments.export', [
            'method' => PaymentMethod::MobileMoney->value,
        ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Payment #')
        ->toContain('Ada Export')
        ->toContain('Mobile Money')
        ->toContain('MM-EXPORT-1')
        ->toContain((string) $included->id)
        ->not->toContain('CASH-EXPORT-2');

    expect(array_filter(explode("\n", trim($content))))->toHaveCount(2);
});

test('the register can be narrowed to one borrower and the export follows', function () {
    $admin = User::factory()->admin()->create();

    $borrower = Borrower::factory()->create(['name' => 'Ada Ledger']);
    $borrowerLoan = Loan::factory()->for($borrower)->withStatus(LoanStatus::Active)->create();
    $borrowerPayment = Payment::factory()->for($borrowerLoan)->create(['amount' => 300]);

    $otherLoan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $otherLoan->borrower->update(['name' => 'Grace Other']);
    Payment::factory()->for($otherLoan)->create(['amount' => 900]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['borrower' => $borrower->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $borrowerPayment->id)
            ->where('currentBorrower.id', $borrower->id)
            ->where('currentBorrower.name', 'Ada Ledger')
            ->where('counts.all', 1)
            ->where('summary.received', '300.00')
        );

    $content = $this->actingAs($admin)
        ->get(route('admin.payments.export', ['borrower' => $borrower->id]))
        ->assertOk()
        ->streamedContent();

    expect($content)
        ->toContain('Ada Ledger')
        ->toContain('300.00')
        ->not->toContain('Grace Other')
        ->not->toContain('900.00');
});

test('an unknown borrower filter is ignored', function () {
    $admin = User::factory()->admin()->create();
    Payment::factory()->create(['amount' => 125]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['borrower' => 999999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('currentBorrower', null)
        );
});

test('the register can be narrowed to one loan and the export follows', function () {
    $admin = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $loan->borrower->update(['name' => 'Ada Loan']);
    $loanPayment = Payment::factory()->for($loan)->create(['amount' => 320]);

    $otherLoan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $otherLoan->borrower->update(['name' => 'Grace Loan']);
    Payment::factory()->for($otherLoan)->create(['amount' => 640]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['loan' => $loan->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $loanPayment->id)
            ->where('currentLoan.id', $loan->id)
            ->where('currentLoan.label', fn (string $label): bool => str_contains($label, "Loan #{$loan->id}"))
            ->where('counts.all', 1)
            ->where('summary.received', '320.00')
        );

    $content = $this->actingAs($admin)
        ->get(route('admin.payments.export', ['loan' => $loan->id]))
        ->assertOk()
        ->streamedContent();

    expect($content)
        ->toContain('Ada Loan')
        ->toContain('320.00')
        ->not->toContain('Grace Loan')
        ->not->toContain('640.00');
});

test('an unknown loan filter is ignored', function () {
    $admin = User::factory()->admin()->create();
    Payment::factory()->create(['amount' => 125]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['loan' => 999999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('currentLoan', null)
        );
});

test('regular users cannot open or export the payments register', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.payments.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.payments.export'))
        ->assertForbidden();
});
