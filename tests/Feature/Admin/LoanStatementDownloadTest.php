<?php

use App\Enums\PaymentMethod;
use App\Models\User;
use App\Services\RepaymentService;
use Illuminate\Support\Facades\Notification;

test('admins can download a loan statement as CSV', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 10, 'fee' => 5, 'due_date' => '2026-07-01'],
    ]);
    $loan->borrower->update([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    app(RepaymentService::class)->recordPayment(
        $loan,
        $admin,
        50,
        PaymentMethod::Cash,
    );

    $response = $this->actingAs($admin)
        ->get(route('admin.loans.statement.download', ['loan' => $loan]));

    $response->assertOk()
        ->assertDownload("loan-{$loan->id}-statement.csv");

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Loan statement')
        ->toContain('#'.$loan->id)
        ->toContain('Ada Lovelace')
        ->toContain('Schedule')
        ->toContain('2026-07-01')
        ->toContain('Payments')
        ->toContain('50.00')
        ->toContain('Posted');
});

test('statement downloads do not require an email address', function () {
    $admin = User::factory()->admin()->create();
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 10, 'fee' => 5, 'due_date' => '2026-07-01'],
    ]);
    $loan->borrower->update(['email' => null]);

    $this->actingAs($admin)
        ->get(route('admin.loans.statement.download', ['loan' => $loan]))
        ->assertOk()
        ->assertDownload("loan-{$loan->id}-statement.csv");
});

test('regular users cannot download loan statements', function () {
    $user = User::factory()->create();
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 10, 'fee' => 5, 'due_date' => '2026-07-01'],
    ]);

    $this->actingAs($user)
        ->get(route('admin.loans.statement.download', ['loan' => $loan]))
        ->assertForbidden();
});
