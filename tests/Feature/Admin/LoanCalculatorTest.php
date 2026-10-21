<?php

use App\Enums\InterestMethod;
use App\Models\LoanProduct;
use App\Models\User;

test('admins can open the loan calculator', function () {
    $admin = User::factory()->admin()->create();

    LoanProduct::factory()->create(['name' => 'Starter']);

    $this->actingAs($admin)
        ->get(route('admin.loans.calculator'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/calculator')
            ->has('loanProducts', 1)
            ->where('preview', null)
            ->where('filters.loan_product_id', '')
        );
});

test('the calculator previews the full schedule for valid terms', function () {
    $admin = User::factory()->admin()->create();

    $product = LoanProduct::factory()->create([
        'interest_method' => InterestMethod::Flat->value,
        'interest_rate' => 12,
        'min_amount' => 1000,
        'max_amount' => 10000,
        'min_term_months' => 3,
        'max_term_months' => 12,
        'processing_fee_rate' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.loans.calculator', [
            'loan_product_id' => $product->id,
            'principal' => 1200,
            'term_months' => 6,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preview.periods', 6)
            ->has('preview.installments', 6)
            ->where('preview.totals.principal', 1200)
            ->where('preview.totals.interest', 72)
            ->where('preview.totals.total', 1272)
        );
});

test('the calculator requires all three terms once one is provided', function () {
    $admin = User::factory()->admin()->create();

    $product = LoanProduct::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.calculator', [
            'loan_product_id' => $product->id,
        ]))
        ->assertSessionHasErrors(['principal', 'term_months']);
});

test('the calculator rejects terms outside the product limits', function () {
    $admin = User::factory()->admin()->create();

    $product = LoanProduct::factory()->create([
        'min_amount' => 1000,
        'max_amount' => 10000,
        'min_term_months' => 3,
        'max_term_months' => 12,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.loans.calculator', [
            'loan_product_id' => $product->id,
            'principal' => 500,
            'term_months' => 24,
        ]))
        ->assertSessionHasErrors(['principal', 'term_months']);
});

test('regular users cannot open the loan calculator', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.loans.calculator'))
        ->assertForbidden();
});
