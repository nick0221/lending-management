<?php

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;

test('the loan list exposes the abilities for every row', function () {
    $admin = User::factory()->admin()->create();
    Loan::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/index')
            ->has('loans.data', 1)
            ->where('loans.data.0.can.view', true)
            ->where('loans.data.0.can.delete', true)
            ->has('statuses')
        );
});

test('the loan list can be filtered by status', function () {
    $admin = User::factory()->admin()->create();
    Loan::factory()->pending()->create(['principal' => 1111]);
    Loan::factory()->approved()->create(['principal' => 2222]);

    $this->actingAs($admin)
        ->get(route('admin.loans.index', ['status' => LoanStatus::Approved->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.principal', '2222.00')
            ->where('currentStatus', 'approved')
        );
});

test('the loan list exposes per-status counts', function () {
    $admin = User::factory()->admin()->create();
    Loan::factory()->pending()->count(2)->create();
    Loan::factory()->approved()->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/index')
            ->where('counts.all', 3)
            ->where('counts.pending', 2)
            ->where('counts.approved', 1)
            ->where('counts.rejected', 0)
        );
});

test('the loan list can be searched by borrower name or loan id', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create(['name' => 'Ada Lovelace']);
    $match = Loan::factory()->create(['borrower_id' => $borrower->id]);
    $other = Loan::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.index', ['search' => 'Ada Lovelace']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/index')
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $match->id)
            ->where('search', 'Ada Lovelace')
            ->where('counts.all', 1)
        );

    $this->actingAs($admin)
        ->get(route('admin.loans.index', ['search' => (string) $other->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $other->id)
        );
});

test('admins can originate a loan and the product terms are snapshotted', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create();
    $product = LoanProduct::factory()->create([
        'interest_method' => InterestMethod::DecliningBalance->value,
        'interest_rate' => 18,
        'processing_fee_rate' => 2.5,
        'min_amount' => 1000,
        'max_amount' => 100000,
        'min_term_months' => 6,
        'max_term_months' => 48,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.loans.store'), [
        'borrower_id' => $borrower->id,
        'loan_product_id' => $product->id,
        'principal' => 25000,
        'term_months' => 24,
        'notes' => 'First application.',
    ]);

    $response->assertSessionHasNoErrors();

    $loan = Loan::where('borrower_id', $borrower->id)->firstOrFail();

    $response->assertRedirect(route('admin.loans.show', $loan));

    expect($loan->status)->toBe(LoanStatus::Pending)
        ->and($loan->interest_method)->toBe(InterestMethod::DecliningBalance)
        ->and($loan->interest_rate)->toBe('18.0000')
        ->and($loan->processing_fee_rate)->toBe('2.5000')
        ->and($loan->term_months)->toBe(24)
        ->and($loan->applied_at)->not->toBeNull();

    $product->update(['interest_rate' => 30, 'interest_method' => InterestMethod::Flat->value]);

    expect($loan->fresh()->interest_rate)->toBe('18.0000')
        ->and($loan->fresh()->interest_method)->toBe(InterestMethod::DecliningBalance);
});

test('originating a loan validates the requested terms against the product', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create();
    $product = LoanProduct::factory()->create([
        'min_amount' => 5000,
        'max_amount' => 10000,
        'min_term_months' => 6,
        'max_term_months' => 12,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.loans.store'), [
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'principal' => 1000,
            'term_months' => 24,
        ])
        ->assertSessionHasErrors(['principal', 'term_months']);

    expect(Loan::count())->toBe(0);
});

test('a loan cannot be originated from an inactive product', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create();
    $product = LoanProduct::factory()->inactive()->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.store'), [
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'principal' => 5000,
            'term_months' => 12,
        ])
        ->assertSessionHasErrors('loan_product_id');
});

test('the loan detail exposes the approve and reject abilities', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->pending()->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/show')
            ->where('loan.id', $loan->id)
            ->where('can.approve', true)
            ->where('can.reject', true)
        );
});

test('admins can approve a pending loan', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->pending()->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.approve', $loan))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.loans.show', $loan));

    $loan->refresh();

    expect($loan->status)->toBe(LoanStatus::Approved)
        ->and($loan->approved_at)->not->toBeNull();
});

test('admins can reject a pending loan', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->pending()->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.reject', $loan))
        ->assertSessionHasNoErrors();

    $loan->refresh();

    expect($loan->status)->toBe(LoanStatus::Rejected)
        ->and($loan->rejected_at)->not->toBeNull();
});

test('an approved loan cannot be approved again', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->approved()->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.approve', $loan))
        ->assertSessionHasErrors('status');

    expect($loan->fresh()->status)->toBe(LoanStatus::Approved);
});

test('admins can delete a loan', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.loans.destroy', $loan))
        ->assertRedirect(route('admin.loans.index'));

    expect($loan->fresh())->toBeNull();
});
