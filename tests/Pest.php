<?php

use App\Enums\LoanStatus;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Build an active loan with a hand-written, dated schedule so overdue and
 * penalty behaviour can be asserted against exact figures.
 *
 * @param  array<int, array{principal: float|int, interest: float|int, fee: float|int, due_date: string}>  $installments
 */
function activeLoanWithDatedSchedule(array $installments, float $penaltyRate = 0, int $gracePeriodDays = 0): Loan
{
    $loan = Loan::factory()
        ->withStatus(LoanStatus::Active)
        ->create([
            'principal' => array_sum(array_column($installments, 'principal')),
            'penalty_rate' => $penaltyRate,
            'grace_period_days' => $gracePeriodDays,
        ]);

    foreach ($installments as $index => $row) {
        $loan->installments()->create([
            'sequence' => $index + 1,
            'due_date' => $row['due_date'],
            'principal_due' => $row['principal'],
            'interest_due' => $row['interest'],
            'fee_due' => $row['fee'],
            'balance' => 0,
        ]);
    }

    return $loan;
}
