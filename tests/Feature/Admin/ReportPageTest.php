<?php

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\User;

test('the reports page defaults to the portfolio tab', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/index')
            ->where('currentTab', 'portfolio')
            ->has('summary')
            ->has('aging')
            ->has('risk')
            ->has('collections')
            ->has('loans')
            ->has('products')
        );
});

test('the reports page selects the requested tab', function (string $tab) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['tab' => $tab]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentTab', $tab));
})->with(['aging', 'collections', 'arrears', 'products']);

test('the reports page falls back to the portfolio tab for an unknown tab', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['tab' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentTab', 'portfolio'));
});

test('the portfolio print view renders the summary', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.print'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/print')
            ->where('organisation', config('app.name'))
            ->has('generated_at')
            ->has('filters')
            ->has('summary')
            ->has('aging')
            ->has('risk')
            ->has('collections')
        );
});

test('non-staff cannot open the portfolio print view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.reports.print'))
        ->assertForbidden();
});

test('product performance rolls loans and payments up by product', function () {
    $admin = User::factory()->admin()->create();
    $product = LoanProduct::factory()->create(['name' => 'Salary Advance']);

    $loan = Loan::factory()
        ->forProduct($product)
        ->withStatus(LoanStatus::Active)
        ->create([
            'principal' => '5000.00',
            'disbursed_at' => now(),
        ]);

    Payment::factory()->for($loan)->create([
        'amount' => '1000.00',
        'payment_date' => today(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['tab' => 'products']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/index')
            ->where('currentTab', 'products')
            ->has('products', 1)
            ->where('products.0.product_id', $product->id)
            ->where('products.0.product', 'Salary Advance')
            ->where('products.0.loans', 1)
            ->where('products.0.open_loans', 1)
            ->where('products.0.disbursed', 5000)
            ->where('products.0.collected', 1000)
            ->where('products.0.collection_rate', 20)
        );
});

test('product performance can be downloaded as a CSV', function () {
    $admin = User::factory()->admin()->create();
    $product = LoanProduct::factory()->create(['name' => 'Salary Advance']);

    Loan::factory()
        ->forProduct($product)
        ->withStatus(LoanStatus::Active)
        ->create([
            'principal' => '5000.00',
            'disbursed_at' => now(),
        ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.reports.download', ['report' => 'products']));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Product')
        ->toContain('Collection rate')
        ->toContain('Salary Advance');
});
