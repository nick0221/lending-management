<?php

use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;

test('staff can search borrowers by name, phone or national ID', function (string $term) {
    $admin = User::factory()->admin()->create();

    $borrower = Borrower::factory()->create([
        'name' => 'Grace Mwangi',
        'phone' => '+254700111222',
        'national_id' => 'ID-99',
    ]);

    Loan::factory()->create([
        'borrower_id' => $borrower->id,
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.search', ['q' => $term]))
        ->assertOk()
        ->assertJsonPath('borrowers.0.id', $borrower->id)
        ->assertJsonPath('loans.0.borrower', 'Grace Mwangi');
})->with(['Grace', '+254700111222', 'ID-99', '  Grace  ']);

test('staff can open a loan by its number', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->create();

    $this->actingAs($admin)
        ->getJson(route('admin.search', ['q' => "#{$loan->id}"]))
        ->assertOk()
        ->assertJsonCount(1, 'loans')
        ->assertJsonPath('loans.0.id', $loan->id);
});

test('a blank search returns no results', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson(route('admin.search', ['q' => '  ']))
        ->assertOk()
        ->assertExactJson([
            'query' => '',
            'borrowers' => [],
            'loans' => [],
            'payments' => [],
            'disbursements' => [],
        ]);
});

test('search results are limited to five per group', function () {
    $admin = User::factory()->admin()->create();

    Borrower::factory()->count(6)->create(['name' => 'Repeated Name']);

    $this->actingAs($admin)
        ->getJson(route('admin.search', ['q' => 'Repeated Name']))
        ->assertOk()
        ->assertJsonCount(5, 'borrowers');
});

test('staff can find a payment by its reference', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create(['name' => 'Grace Mwangi']);
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id]);

    $payment = Payment::factory()->for($loan)->create([
        'reference' => 'PAY-4242',
        'amount' => '750.00',
        'payment_date' => today(),
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.search', ['q' => 'PAY-4242']))
        ->assertOk()
        ->assertJsonCount(1, 'payments')
        ->assertJsonPath('payments.0.id', $payment->id)
        ->assertJsonPath('payments.0.amount', 750)
        ->assertJsonPath('payments.0.reference', 'PAY-4242')
        ->assertJsonPath('payments.0.borrower', 'Grace Mwangi')
        ->assertJsonPath('payments.0.loan_id', $loan->id)
        ->assertJsonPath('payments.0.reversed', false);
});

test('staff can find a payment by its number and open the loan payments tab', function () {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('admin.search', ['q' => "#{$payment->id}"]))
        ->assertOk()
        ->assertJsonPath('payments.0.id', $payment->id);

    expect($response->json('payments.0.url'))
        ->toContain("loans/{$payment->loan_id}")
        ->toContain('tab=payments');
});

test('a reversed payment is flagged in search results', function () {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->create([
        'reference' => 'PAY-REVERSED',
        'reversed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.search', ['q' => 'PAY-REVERSED']))
        ->assertOk()
        ->assertJsonPath('payments.0.reversed', true);
});

test('staff can find a payout by its reference and open the disbursements tab', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create(['name' => 'Grace Mwangi']);
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id]);

    $disbursement = Disbursement::factory()->for($loan)->create([
        'reference' => 'DSB-4242',
        'amount' => '1500.00',
        'disbursed_at' => today(),
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.search', ['q' => 'DSB-4242']))
        ->assertOk()
        ->assertJsonCount(1, 'disbursements')
        ->assertJsonPath('disbursements.0.id', $disbursement->id)
        ->assertJsonPath('disbursements.0.amount', 1500)
        ->assertJsonPath('disbursements.0.reference', 'DSB-4242')
        ->assertJsonPath('disbursements.0.borrower', 'Grace Mwangi')
        ->assertJsonPath('disbursements.0.loan_id', $loan->id);

    expect($response->json('disbursements.0.url'))
        ->toContain("loans/{$loan->id}")
        ->toContain('tab=disbursements');
});

test('staff can find a payout by its number', function () {
    $admin = User::factory()->admin()->create();
    $disbursement = Disbursement::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('admin.search', ['q' => "#{$disbursement->id}"]))
        ->assertOk()
        ->assertJsonPath('disbursements.0.id', $disbursement->id);

    expect($response->json('disbursements.0.url'))
        ->toContain("loans/{$disbursement->loan_id}")
        ->toContain('tab=disbursements');
});

test('non-staff cannot use global search', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('admin.search', ['q' => 'Grace']))
        ->assertForbidden();
});
