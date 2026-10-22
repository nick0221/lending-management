<?php

use App\Models\Borrower;
use App\Models\Loan;
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

test('non-staff cannot use global search', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('admin.search', ['q' => 'Grace']))
        ->assertForbidden();
});
