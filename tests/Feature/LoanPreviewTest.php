<?php

use App\Enums\InterestMethod;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;

test('staff can preview a loan schedule without storing it', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();
    $product = LoanProduct::factory()->create([
        'interest_method' => InterestMethod::Flat->value,
        'interest_rate' => 12,
        'processing_fee_rate' => 1,
        'min_amount' => 1000,
        'max_amount' => 100000,
        'min_term_months' => 6,
        'max_term_months' => 36,
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.loans.preview'), [
        'loan_product_id' => $product->id,
        'principal' => 12000,
        'term_months' => 12,
    ]);

    $response->assertOk()
        ->assertJsonCount(3, 'installments')
        ->assertJsonPath('periods', 12)
        ->assertJsonPath('installments.0.sequence', 1)
        ->assertJsonPath('installments.0.due_date', '2026-07-01')
        ->assertJsonStructure([
            'installments' => [['sequence', 'due_date', 'principal_due', 'interest_due', 'fee_due', 'balance', 'total']],
            'totals' => ['principal', 'interest', 'fee', 'total'],
        ]);

    $totals = $response->json('totals');

    expect((float) $totals['principal'])->toBe(12000.0)
        ->and((float) $totals['interest'])->toBe(1440.0)
        ->and((float) $totals['fee'])->toBe(120.0)
        ->and((float) $totals['total'])->toBe(13560.0)
        ->and(Loan::count())->toBe(0);
});

test('preview rejects terms outside the product limits', function () {
    $admin = User::factory()->admin()->create();
    $product = LoanProduct::factory()->create([
        'min_amount' => 5000,
        'max_amount' => 10000,
        'min_term_months' => 6,
        'max_term_months' => 12,
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.loans.preview'), [
            'loan_product_id' => $product->id,
            'principal' => 1000,
            'term_months' => 24,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['principal', 'term_months']);
});

test('preview rejects inactive products', function () {
    $admin = User::factory()->admin()->create();
    $product = LoanProduct::factory()->inactive()->create();

    $this->actingAs($admin)
        ->postJson(route('admin.loans.preview'), [
            'loan_product_id' => $product->id,
            'principal' => 5000,
            'term_months' => 12,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('loan_product_id');
});

test('regular users cannot preview loan schedules', function () {
    $user = User::factory()->create();
    $product = LoanProduct::factory()->create();

    $this->actingAs($user)
        ->postJson(route('admin.loans.preview'), [
            'loan_product_id' => $product->id,
            'principal' => 5000,
            'term_months' => 12,
        ])
        ->assertForbidden();
});
