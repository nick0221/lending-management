<?php

use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReportingService;
use Carbon\CarbonImmutable;

/**
 * Two active loans: one installment settled in full and one installment due
 * today, plus an upcoming installment outside the collection window.
 *
 * @return array{0: Loan, 1: Loan}
 */
function reportingFixture(): array
{
    $settled = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 20, 'fee' => 5, 'due_date' => '2026-05-01'],
        ['principal' => 100, 'interest' => 20, 'fee' => 5, 'due_date' => '2026-06-01'],
    ]);

    $payment = Payment::factory()->create([
        'loan_id' => $settled->id,
        'amount' => 125,
        'payment_date' => '2026-05-01',
        'recorded_by' => null,
    ]);

    $payment->allocations()->create([
        'loan_id' => $settled->id,
        'installment_id' => $settled->installments()->where('sequence', 1)->value('id'),
        'sequence' => 1,
        'principal_allocated' => 100,
        'interest_allocated' => 20,
        'fee_allocated' => 5,
        'penalty_allocated' => 0,
    ]);

    Disbursement::factory()->create([
        'loan_id' => $settled->id,
        'amount' => 1000,
        'recorded_by' => null,
    ]);

    $upcoming = activeLoanWithDatedSchedule([
        ['principal' => 50, 'interest' => 10, 'fee' => 0, 'due_date' => '2026-07-15'],
    ]);

    return [$settled, $upcoming];
}

test('the portfolio summary reconciles with the underlying records', function () {
    $this->travelTo('2026-06-01');

    reportingFixture();

    $summary = app(ReportingService::class)->portfolioSummary();

    expect($summary['borrowers'])->toBe(2)
        ->and($summary['loans'])->toBe(2)
        ->and($summary['open_loans'])->toBe(2)
        ->and($summary['active_loans'])->toBe(2)
        ->and($summary['defaulted_loans'])->toBe(0)
        ->and($summary['disbursed'])->toBe(1000.0)
        ->and($summary['outstanding'])->toBe(185.0)
        ->and($summary['outstanding_principal'])->toBe(150.0)
        ->and($summary['outstanding_interest'])->toBe(30.0)
        ->and($summary['outstanding_fee'])->toBe(5.0)
        ->and($summary['outstanding_penalty'])->toBe(0.0)
        ->and($summary['arrears'])->toBe(0.0)
        ->and($summary['collected'])->toBe(125.0)
        ->and($summary['collected_principal'])->toBe(100.0)
        ->and($summary['collected_interest'])->toBe(20.0)
        ->and($summary['collected_fee'])->toBe(5.0)
        ->and($summary['collected_penalty'])->toBe(0.0);
});

test('the loan report total reconciles with the portfolio summary', function () {
    $this->travelTo('2026-06-01');

    reportingFixture();

    $reporting = app(ReportingService::class);

    expect(round($reporting->loanRows()->sum('outstanding'), 2))
        ->toBe($reporting->portfolioSummary()['outstanding']);
});

test('undisbursed loans keep their schedule but report no receivable', function () {
    $this->travelTo('2026-06-01');

    reportingFixture();

    $approved = activeLoanWithDatedSchedule([
        ['principal' => 500, 'interest' => 50, 'fee' => 0, 'due_date' => '2026-05-01'],
    ]);
    $approved->forceFill(['status' => LoanStatus::Approved])->save();

    $reporting = app(ReportingService::class);

    expect($reporting->portfolioSummary()['outstanding'])->toBe(185.0)
        ->and($reporting->agingBuckets()['days_31_60'])->toBe(0.0)
        ->and($reporting->portfolioAtRisk()['par']['amount'])->toBe(0.0);

    $approvedRow = $reporting->loanRows()->firstWhere('id', $approved->id);

    expect($approvedRow['outstanding'])->toBe(0.0)
        ->and($approvedRow['arrears'])->toBe(0.0)
        ->and($approvedRow['is_open'])->toBeFalse();
});

test('aging buckets group outstanding balances by days past due', function () {
    $this->travelTo('2026-06-01');

    activeLoanWithDatedSchedule([
        ['principal' => 400, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-20'],
    ]);

    activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-20'],
    ]);

    activeLoanWithDatedSchedule([
        ['principal' => 200, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-04-20'],
    ]);

    activeLoanWithDatedSchedule([
        ['principal' => 300, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-03-01'],
    ]);

    $reporting = app(ReportingService::class);

    expect($reporting->agingBuckets())->toBe([
        'current' => 400.0,
        'days_1_30' => 100.0,
        'days_31_60' => 200.0,
        'days_61_90' => 0.0,
        'days_over_90' => 300.0,
    ]);
});

test('portfolio at risk measures overdue balances against the portfolio', function () {
    $this->travelTo('2026-06-01');

    activeLoanWithDatedSchedule([
        ['principal' => 400, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-20'],
    ]);

    activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-20'],
    ]);

    activeLoanWithDatedSchedule([
        ['principal' => 200, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-04-20'],
    ]);

    activeLoanWithDatedSchedule([
        ['principal' => 300, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-03-01'],
    ]);

    $risk = app(ReportingService::class)->portfolioAtRisk();

    expect($risk['outstanding'])->toBe(1000.0)
        ->and($risk['par']['amount'])->toBe(600.0)
        ->and($risk['par']['ratio'])->toBe(60.0)
        ->and($risk['par30']['amount'])->toBe(500.0)
        ->and($risk['par30']['ratio'])->toBe(50.0)
        ->and($risk['par60']['amount'])->toBe(300.0)
        ->and($risk['par60']['ratio'])->toBe(30.0)
        ->and($risk['par90']['amount'])->toBe(300.0)
        ->and($risk['par90']['ratio'])->toBe(30.0);
});

test('collections split today, this week and overdue installments', function () {
    $this->travelTo('2026-06-01');

    activeLoanWithDatedSchedule([
        ['principal' => 50, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-05-30'],
        ['principal' => 10, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-01'],
        ['principal' => 20, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-05'],
        ['principal' => 30, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-08'],
        ['principal' => 40, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-06-09'],
    ]);

    $collections = app(ReportingService::class)->collections();

    expect($collections['as_of'])->toBe('2026-06-01')
        ->and($collections['due_today'])->toBe(10.0)
        ->and($collections['due_this_week'])->toBe(60.0)
        ->and($collections['overdue'])->toBe(50.0)
        ->and($collections['upcoming'])->toHaveCount(3)
        ->and($collections['overdue_installments'])->toHaveCount(1)
        ->and($collections['overdue_installments'][0]['days_past_due'])->toBe(2);
});

test('the reports page exposes the portfolio figures', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    reportingFixture();

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/index')
            ->where('summary.outstanding', 185)
            ->where('summary.collected', 125)
            ->where('aging.current', 185)
            ->where('risk.par.ratio', 0)
            ->where('collections.due_today', 125)
            ->has('loans', 2)
            ->where('loans.0.outstanding', 125)
            ->where('loans.1.outstanding', 60)
        );
});

test('the report period narrows every report to loans applied inside it', function () {
    $this->travelTo('2026-06-01');

    [$settled, $upcoming] = reportingFixture();

    $settled->forceFill(['applied_at' => '2026-05-01'])->save();
    $upcoming->forceFill(['applied_at' => '2026-06-01'])->save();

    $reporting = app(ReportingService::class)->forPeriod(
        CarbonImmutable::parse('2026-06-01'),
        CarbonImmutable::parse('2026-06-01'),
    );

    $summary = $reporting->portfolioSummary();

    expect($reporting->loanRows())->toHaveCount(1)
        ->and($reporting->loanRows()->first()['id'])->toBe($upcoming->id)
        ->and($summary['borrowers'])->toBe(1)
        ->and($summary['loans'])->toBe(1)
        ->and($summary['outstanding'])->toBe(60.0)
        ->and($summary['collected'])->toBe(0.0)
        ->and($summary['disbursed'])->toBe(0.0)
        ->and($reporting->collections()['due_today'])->toBe(0.0)
        ->and($reporting->collections()['upcoming'])->toHaveCount(0);
});

test('the reports page passes the selected period to the page', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    reportingFixture();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['from' => '2026-06-01', 'to' => '2026-06-01']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/index')
            ->where('filters.from', '2026-06-01')
            ->where('filters.to', '2026-06-01')
            ->has('loans', 2)
        );
});

test('the reports page rejects a to date before the from date', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['from' => '2026-06-10', 'to' => '2026-06-01']))
        ->assertSessionHasErrors('to');
});

test('the admin dashboard exposes the portfolio overview', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    reportingFixture();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('summary.outstanding', 185)
            ->where('collections.due_today', 125)
            ->where('risk.par.ratio', 0)
            ->has('recentPayments', 1)
            ->where('recentPayments.0.amount', 125)
        );
});

test('the summary export contains the on-screen totals', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    reportingFixture();

    $response = $this->actingAs($admin)->get(route('admin.reports.download', ['report' => 'summary']));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Section,Metric,Value')
        ->toContain('Portfolio,"Outstanding total",185.00')
        ->toContain('Portfolio,"Collected total",125.00')
        ->toContain('Collections,"Due today",125.00')
        ->toContain('Risk,"Portfolio at risk %",0.00%');
});

test('the loan export lists one row per loan', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    reportingFixture();

    $content = $this->actingAs($admin)
        ->get(route('admin.reports.download', ['report' => 'loans']))
        ->streamedContent();

    $lines = explode("\n", trim($content));

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toContain('Borrower,Product,Principal,Status')
        ->and($lines[1])->toContain(',200.00,Active,')
        ->and($lines[1])->toContain(',125.00,125.00,100.00,20.00,5.00,0.00,0.00,0');
});

test('the collections export lists due and overdue installments', function () {
    $this->travelTo('2026-06-01');

    $admin = User::factory()->admin()->create();

    reportingFixture();

    $content = $this->actingAs($admin)
        ->get(route('admin.reports.download', ['report' => 'collections']))
        ->streamedContent();

    $lines = explode("\n", trim($content));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toBe('Borrower,Loan,Installment,"Due date",Amount,"Days past due",Status')
        ->and($lines[1])->toContain(',2026-06-01,125.00,0,Due');
});

test('an unknown report export is not found', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/reports/download/bogus')
        ->assertNotFound();
});

test('the borrower dashboard summarises the linked account', function () {
    $this->travelTo('2026-06-01');

    $user = User::factory()->create();
    $borrower = Borrower::factory()->forUser($user)->verified()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create([
        'borrower_id' => $borrower->id,
    ]);

    $loan->installments()->create([
        'sequence' => 1,
        'due_date' => '2026-06-05',
        'principal_due' => 100,
        'interest_due' => 20,
        'fee_due' => 0,
        'balance' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('summary.borrower.id', $borrower->id)
            ->where('summary.loan_count', 1)
            ->where('summary.open_loan_count', 1)
            ->where('summary.outstanding', 120)
            ->where('summary.arrears', 0)
            ->where('summary.next_due.due_date', '2026-06-05')
            ->where('summary.next_due.amount', 120)
            ->has('summary.upcoming', 1)
            ->has('summary.loans', 1)
            ->has('summary.payments', 0)
        );
});

test('users without a borrower profile get an empty dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('summary', null)
        );
});

test('a reversed payment is excluded from collected totals and the payment feed', function () {
    $this->travelTo('2026-06-01');

    [$settled] = reportingFixture();

    $payment = $settled->payments()->firstOrFail();
    $payment->allocations()->delete();
    $payment->forceFill([
        'reversed_at' => now(),
        'reversal_reason' => 'Recorded twice.',
    ])->save();

    $summary = app(ReportingService::class)->portfolioSummary();

    expect($summary['collected'])->toBe(0.0)
        ->and($summary['collected_principal'])->toBe(0.0)
        ->and($summary['collected_interest'])->toBe(0.0)
        ->and($summary['collected_fee'])->toBe(0.0)
        ->and($summary['outstanding'])->toBe(310.0)
        ->and(app(ReportingService::class)->recentPayments())->toBeEmpty();
});
