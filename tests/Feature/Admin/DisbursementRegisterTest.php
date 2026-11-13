<?php

use App\Enums\DisbursementMethod;
use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\User;

test('the disbursements register lists every payout newest first', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $loan->borrower->update(['name' => 'Ada Payout', 'phone' => '+1-555-0100']);

    $older = Disbursement::factory()->for($loan)->create([
        'amount' => 1000,
        'disbursed_at' => today()->subDays(5),
        'recorded_by' => $admin->id,
    ]);
    $newer = Disbursement::factory()->for($loan)->create([
        'amount' => 3000,
        'method' => DisbursementMethod::Cash->value,
        'disbursed_at' => today(),
        'recorded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.disbursements.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/disbursements/index')
            ->has('disbursements.data', 2)
            ->where('disbursements.data.0.id', $newer->id)
            ->where('disbursements.data.0.borrower_name', 'Ada Payout')
            ->where('disbursements.data.0.method_label', 'Cash')
            ->where('disbursements.data.1.id', $older->id)
            ->where('summary.count', 2)
            ->where('summary.total', '4000.00')
            ->where('summary.average', '2000.00')
        );
});

test('the register filters by method, payment date and search', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $loan->borrower->update([
        'name' => 'Ada Filter',
        'phone' => '+1-555-0111',
        'national_id' => 'NID-DISBURSE-1',
    ]);

    $cash = Disbursement::factory()->for($loan)->create([
        'method' => DisbursementMethod::Cash->value,
        'reference' => 'CASH-1',
        'disbursed_at' => today()->subDays(2),
    ]);

    Disbursement::factory()->for($loan)->create([
        'method' => DisbursementMethod::BankTransfer->value,
        'reference' => 'BANK-2',
        'disbursed_at' => today()->subDays(40),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.disbursements.index', [
            'method' => DisbursementMethod::Cash->value,
            'from' => today()->subDays(7)->toDateString(),
            'to' => today()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('disbursements.data', 1)
            ->where('disbursements.data.0.id', $cash->id)
            ->where('currentMethod', DisbursementMethod::Cash->value)
            ->where('summary.count', 1)
        );

    $this->actingAs($admin)
        ->get(route('admin.disbursements.index', ['search' => 'BANK-2']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('disbursements.data', 1)
            ->where('disbursements.data.0.reference', 'BANK-2')
        );

    $this->actingAs($admin)
        ->get(route('admin.disbursements.index', ['search' => 'NID-DISBURSE-1']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('disbursements.data', 2)
        );
});

test('the register filters by the staff member who recorded the payout', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $officer = User::factory()->create(['name' => 'Sam Officer']);
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    Disbursement::factory()->for($loan)->create([
        'amount' => 900,
        'recorded_by' => $admin->id,
    ]);
    $theirs = Disbursement::factory()->for($loan)->create([
        'amount' => 1500,
        'recorded_by' => $officer->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.disbursements.index', ['recorded_by' => $officer->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('disbursements.data', 1)
            ->where('disbursements.data.0.id', $theirs->id)
            ->where('currentRecorder', $officer->id)
            ->has('recorders', 2)
        );

    $response = $this->actingAs($admin)
        ->get(route('admin.disbursements.export', ['recorded_by' => $officer->id]));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Sam Officer')
        ->not->toContain('Ada Admin');
});

test('the register export honours the active filters', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $loan->borrower->update(['name' => 'Ada Export']);

    $included = Disbursement::factory()->for($loan)->create([
        'amount' => 111,
        'method' => DisbursementMethod::MobileMoney->value,
        'reference' => 'MM-EXPORT-1',
        'disbursed_at' => today()->subDays(2),
    ]);
    Disbursement::factory()->for($loan)->create([
        'amount' => 222,
        'method' => DisbursementMethod::Cash->value,
        'reference' => 'CASH-EXPORT-2',
        'disbursed_at' => today()->subDays(2),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.disbursements.export', [
            'method' => DisbursementMethod::MobileMoney->value,
        ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Disbursement #')
        ->toContain('Ada Export')
        ->toContain('Mobile Money')
        ->toContain('MM-EXPORT-1')
        ->toContain((string) $included->id)
        ->not->toContain('CASH-EXPORT-2');

    expect(array_filter(explode("\n", trim($content))))->toHaveCount(2);
});

test('the register can be narrowed to one borrower or loan and the export follows', function () {
    $admin = User::factory()->admin()->create();

    $borrower = Borrower::factory()->create(['name' => 'Ada Payout']);
    $borrowerLoan = Loan::factory()->for($borrower)->withStatus(LoanStatus::Active)->create();
    $borrowerPayout = Disbursement::factory()->for($borrowerLoan)->create(['amount' => 300]);

    $otherLoan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $otherLoan->borrower->update(['name' => 'Grace Other']);
    Disbursement::factory()->for($otherLoan)->create(['amount' => 900]);

    $this->actingAs($admin)
        ->get(route('admin.disbursements.index', ['borrower' => $borrower->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('disbursements.data', 1)
            ->where('disbursements.data.0.id', $borrowerPayout->id)
            ->where('currentBorrower.id', $borrower->id)
            ->where('summary.total', '300.00')
        );

    $this->actingAs($admin)
        ->get(route('admin.disbursements.index', ['loan' => $borrowerLoan->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('disbursements.data', 1)
            ->where('currentLoan.id', $borrowerLoan->id)
            ->where('currentLoan.label', fn (string $label): bool => str_contains($label, "Loan #{$borrowerLoan->id}"))
            ->where('summary.total', '300.00')
        );

    $content = $this->actingAs($admin)
        ->get(route('admin.disbursements.export', ['borrower' => $borrower->id]))
        ->assertOk()
        ->streamedContent();

    expect($content)
        ->toContain('Ada Payout')
        ->toContain('300.00')
        ->not->toContain('Grace Other')
        ->not->toContain('900.00');
});

test('unknown borrower and loan filters are ignored', function () {
    $admin = User::factory()->admin()->create();
    Disbursement::factory()->create(['amount' => 125]);

    $this->actingAs($admin)
        ->get(route('admin.disbursements.index', [
            'borrower' => 999999,
            'loan' => 999999,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('disbursements.data', 1)
            ->where('currentBorrower', null)
            ->where('currentLoan', null)
        );
});

test('regular users cannot open or export the disbursements register', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.disbursements.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.disbursements.export'))
        ->assertForbidden();
});
