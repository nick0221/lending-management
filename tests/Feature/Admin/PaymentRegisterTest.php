<?php

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
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

test('the register searches by borrower, reference and loan number', function () {
    $admin = User::factory()->admin()->create();

    $matchingLoan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $matchingLoan->borrower->update(['name' => 'Grace Reference']);

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

test('regular users cannot open or export the payments register', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.payments.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.payments.export'))
        ->assertForbidden();
});
