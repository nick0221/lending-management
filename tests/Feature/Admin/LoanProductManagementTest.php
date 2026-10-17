<?php

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Models\LoanProduct;
use App\Models\User;

test('the loan product list exposes the abilities for every row', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    LoanProduct::factory()->create(['name' => 'Starter Loan']);

    $this->actingAs($superAdmin)
        ->get(route('admin.loan-products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loan-products/index')
            ->has('loanProducts.data', 1)
            ->where('loanProducts.data.0.name', 'Starter Loan')
            ->where('loanProducts.data.0.can.update', true)
            ->where('loanProducts.data.0.can.delete', true)
        );
});

test('admins can create a loan product', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.loan-products.store'), [
            'name' => 'Premium Loan',
            'description' => 'Top tier product.',
            'interest_method' => InterestMethod::DecliningBalance->value,
            'interest_rate' => 15.5,
            'repayment_frequency' => RepaymentFrequency::Monthly->value,
            'min_amount' => 5000,
            'max_amount' => 100000,
            'min_term_months' => 12,
            'max_term_months' => 48,
            'processing_fee_rate' => 2.5,
            'penalty_rate' => 3.5,
            'grace_period_days' => 5,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.loan-products.index'));

    $loanProduct = LoanProduct::where('name', 'Premium Loan')->first();

    expect($loanProduct)->not->toBeNull()
        ->and($loanProduct->interest_method)->toBe(InterestMethod::DecliningBalance)
        ->and($loanProduct->repayment_frequency)->toBe(RepaymentFrequency::Monthly)
        ->and((float) $loanProduct->penalty_rate)->toBe(3.5)
        ->and($loanProduct->grace_period_days)->toBe(5)
        ->and($loanProduct->is_active)->toBeTrue();
});

test('creating a loan product validates its terms', function () {
    $admin = User::factory()->admin()->create();
    LoanProduct::factory()->create(['name' => 'Existing Loan']);

    $this->actingAs($admin)
        ->post(route('admin.loan-products.store'), [
            'name' => 'Existing Loan',
            'interest_method' => 'not-a-method',
            'interest_rate' => 150,
            'repayment_frequency' => 'not-a-frequency',
            'min_amount' => 10000,
            'max_amount' => 5000,
            'min_term_months' => 24,
            'max_term_months' => 12,
            'processing_fee_rate' => -1,
            'penalty_rate' => -1,
            'grace_period_days' => 500,
        ])
        ->assertSessionHasErrors([
            'name',
            'interest_method',
            'interest_rate',
            'repayment_frequency',
            'max_amount',
            'max_term_months',
            'processing_fee_rate',
            'penalty_rate',
            'grace_period_days',
        ]);
});

test('admins can update a loan product', function () {
    $admin = User::factory()->admin()->create();
    $loanProduct = LoanProduct::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.loan-products.update', $loanProduct), [
            'name' => $loanProduct->name,
            'interest_method' => InterestMethod::DecliningBalance->value,
            'interest_rate' => 9.75,
            'repayment_frequency' => RepaymentFrequency::Biweekly->value,
            'min_amount' => $loanProduct->min_amount,
            'max_amount' => $loanProduct->max_amount,
            'min_term_months' => $loanProduct->min_term_months,
            'max_term_months' => $loanProduct->max_term_months,
            'processing_fee_rate' => $loanProduct->processing_fee_rate,
            'penalty_rate' => 4,
            'grace_period_days' => 10,
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.loan-products.index'));

    $loanProduct->refresh();

    expect($loanProduct->interest_method)->toBe(InterestMethod::DecliningBalance)
        ->and($loanProduct->repayment_frequency)->toBe(RepaymentFrequency::Biweekly)
        ->and((float) $loanProduct->penalty_rate)->toBe(4.0)
        ->and($loanProduct->grace_period_days)->toBe(10)
        ->and($loanProduct->is_active)->toBeFalse();
});

test('admins can delete a loan product', function () {
    $admin = User::factory()->admin()->create();
    $loanProduct = LoanProduct::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.loan-products.destroy', $loanProduct))
        ->assertRedirect(route('admin.loan-products.index'));

    expect($loanProduct->fresh())->toBeNull();
});

test('the active scope only returns active loan products', function () {
    LoanProduct::factory()->create(['name' => 'Active Loan', 'is_active' => true]);
    LoanProduct::factory()->inactive()->create(['name' => 'Dormant Loan']);

    $names = LoanProduct::query()->active()->pluck('name');

    expect($names)->toContain('Active Loan')
        ->and($names)->not->toContain('Dormant Loan');
});
