<?php

use App\Enums\CollectionOutcome;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\CollectionContact;
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
            ->has('collections_trend')
            ->has('loans')
            ->has('products')
            ->has('officers')
            ->has('payment_methods')
        );
});

test('the reports page selects the requested tab', function (string $tab) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['tab' => $tab]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentTab', $tab));
})->with(['aging', 'collections', 'arrears', 'products', 'officers']);

test('the reports page falls back to the portfolio tab for an unknown tab', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['tab' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentTab', 'portfolio'));
});

test('the collections tab reports payments received by month', function () {
    $this->travelTo('2026-11-15 10:00:00');

    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->create();

    Payment::factory()->count(2)->for($loan)->create([
        'amount' => '500.00',
        'payment_date' => '2026-11-03',
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '250.00',
        'payment_date' => '2026-10-04',
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '75.00',
        'payment_date' => '2026-06-10',
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '999.00',
        'payment_date' => '2026-11-05',
        'reversed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['tab' => 'collections']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('collections_trend', 6)
            ->where('collections_trend.0.month', '2026-06')
            ->where('collections_trend.0.amount', 75)
            ->where('collections_trend.4.month', '2026-10')
            ->where('collections_trend.4.amount', 250)
            ->where('collections_trend.4.count', 1)
            ->where('collections_trend.5.month', '2026-11')
            ->where('collections_trend.5.amount', 1000)
            ->where('collections_trend.5.count', 2)
        );
});

test('the collections trend follows the report period', function () {
    $this->travelTo('2026-11-15 10:00:00');

    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->create();

    Payment::factory()->for($loan)->create([
        'amount' => '120.00',
        'payment_date' => '2026-01-10',
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '80.00',
        'payment_date' => '2026-02-10',
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '40.00',
        'payment_date' => '2026-03-10',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'tab' => 'collections',
            'from' => '2026-01-01',
            'to' => '2026-02-28',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('collections_trend', 2)
            ->where('collections_trend.0.month', '2026-01')
            ->where('collections_trend.0.amount', 120)
            ->where('collections_trend.1.month', '2026-02')
            ->where('collections_trend.1.amount', 80)
        );
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

test('officer performance groups collection contacts by officer', function () {
    $admin = User::factory()->admin()->create();
    $officer = User::factory()->admin()->create(['name' => 'Officer One']);
    $loan = Loan::factory()->create();

    CollectionContact::factory()->create([
        'user_id' => $officer->id,
        'loan_id' => $loan->id,
        'outcome' => CollectionOutcome::Contacted->value,
    ]);

    CollectionContact::factory()->promised('2500.00')->create([
        'user_id' => $officer->id,
        'loan_id' => $loan->id,
    ]);

    CollectionContact::factory()->create([
        'user_id' => $officer->id,
        'loan_id' => $loan->id,
        'outcome' => CollectionOutcome::NoAnswer->value,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['tab' => 'officers']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/index')
            ->where('currentTab', 'officers')
            ->has('officers', 1)
            ->where('officers.0.officer', 'Officer One')
            ->where('officers.0.contacts', 3)
            ->where('officers.0.loans', 1)
            ->where('officers.0.promises', 1)
            ->where('officers.0.promised_amount', 2500)
            ->where('officers.0.no_answer', 1)
        );
});

test('officer performance can be downloaded as a CSV', function () {
    $admin = User::factory()->admin()->create();
    $officer = User::factory()->admin()->create(['name' => 'Officer One']);

    CollectionContact::factory()->promised('2500.00')->create([
        'user_id' => $officer->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.reports.download', ['report' => 'officers']));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Officer')
        ->toContain('Promised amount')
        ->toContain('Officer One');
});

test('payment methods report completed and reversed totals for the period', function () {
    $this->travelTo('2026-11-15 10:00:00');

    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->create();

    Payment::factory()->for($loan)->create([
        'amount' => '500.00',
        'method' => PaymentMethod::Cash->value,
        'payment_date' => '2026-11-03',
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '250.00',
        'method' => PaymentMethod::Cash->value,
        'payment_date' => '2026-11-04',
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '900.00',
        'method' => PaymentMethod::BankTransfer->value,
        'payment_date' => '2026-11-05',
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '125.00',
        'method' => PaymentMethod::BankTransfer->value,
        'payment_date' => '2026-11-06',
        'reversed_at' => now(),
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '700.00',
        'method' => PaymentMethod::MobileMoney->value,
        'payment_date' => '2026-08-01',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'tab' => 'collections',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('payment_methods', 2)
            ->where('payment_methods.0.method_label', 'Bank Transfer')
            ->where('payment_methods.0.completed_count', 1)
            ->where('payment_methods.0.completed_amount', 900)
            ->where('payment_methods.0.reversed_count', 1)
            ->where('payment_methods.0.reversed_amount', 125)
            ->where('payment_methods.1.method_label', 'Cash')
            ->where('payment_methods.1.completed_count', 2)
            ->where('payment_methods.1.completed_amount', 750)
        );
});

test('the payment methods report can be downloaded as a CSV', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->create();

    Payment::factory()->for($loan)->create([
        'amount' => '400.00',
        'method' => PaymentMethod::Cash->value,
        'payment_date' => '2026-11-03',
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '90.00',
        'method' => PaymentMethod::Cash->value,
        'payment_date' => '2026-11-04',
        'reversed_at' => now(),
    ]);

    Payment::factory()->for($loan)->create([
        'amount' => '600.00',
        'method' => PaymentMethod::MobileMoney->value,
        'payment_date' => '2026-11-05',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.reports.download', [
            'report' => 'payments',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ]));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Method')
        ->toContain('Completed amount')
        ->toContain('Mobile Money')
        ->toContain('600.00')
        ->toContain('Cash')
        ->toContain('400.00')
        ->toContain('90.00');
});
