<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CsvExporter;
use App\Services\ReportingService;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const EXPORTS = ['summary', 'loans', 'collections', 'arrears'];

    public function __construct(
        private readonly ReportingService $reporting,
        private readonly CsvExporter $csvExporter,
    ) {}

    /**
     * Show the portfolio reports.
     */
    public function index(Request $request): Response
    {
        $currentTab = $request->string('tab')->toString();
        $currentTab = in_array($currentTab, ['portfolio', 'aging', 'collections', 'arrears'], true)
            ? $currentTab
            : 'portfolio';

        $filters = $this->applyPeriod($request);

        return Inertia::render('admin/reports/index', [
            'summary' => $this->reporting->portfolioSummary(),
            'aging' => $this->reporting->agingBuckets(),
            'risk' => $this->reporting->portfolioAtRisk(),
            'collections' => $this->reporting->collections(),
            'loans' => $this->reporting->loanRows()->values()->all(),
            'currentTab' => $currentTab,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the printable portfolio summary.
     */
    public function printSummary(Request $request): Response
    {
        $filters = $this->applyPeriod($request);

        return Inertia::render('admin/reports/print', [
            'organisation' => app(Settings::class)->organisationName(),
            'generated_at' => now()->toDateString(),
            'filters' => $filters,
            'summary' => $this->reporting->portfolioSummary(),
            'aging' => $this->reporting->agingBuckets(),
            'risk' => $this->reporting->portfolioAtRisk(),
            'collections' => $this->reporting->collections(),
        ]);
    }

    /**
     * Download one of the portfolio reports as CSV.
     */
    public function download(Request $request, string $report): StreamedResponse
    {
        abort_unless(in_array($report, self::EXPORTS, true), 404);

        $this->applyPeriod($request);

        return match ($report) {
            'summary' => $this->exportSummary(),
            'loans' => $this->exportLoans(),
            'collections' => $this->exportCollections(),
            'arrears' => $this->exportArrears(),
        };
    }

    /**
     * Apply the optional reporting period to the shared reporting service.
     *
     * @return array{from: string|null, to: string|null}
     */
    private function applyPeriod(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $this->reporting->forPeriod(
            $from !== null ? Date::parse($from) : null,
            $to !== null ? Date::parse($to) : null,
        );

        return ['from' => $from, 'to' => $to];
    }

    private function exportSummary(): StreamedResponse
    {
        $summary = $this->reporting->portfolioSummary();
        $aging = $this->reporting->agingBuckets();
        $risk = $this->reporting->portfolioAtRisk();
        $collections = $this->reporting->collections();

        $money = $this->money(...);
        $percent = fn (float $value): string => number_format($value, 2, '.', '').'%';

        $rows = [
            ['Portfolio', 'Borrowers', $summary['borrowers']],
            ['Portfolio', 'Loans', $summary['loans']],
            ['Portfolio', 'Open loans', $summary['open_loans']],
            ['Portfolio', 'Active loans', $summary['active_loans']],
            ['Portfolio', 'Defaulted loans', $summary['defaulted_loans']],
            ['Portfolio', 'Closed loans', $summary['closed_loans']],
            ['Portfolio', 'Written off loans', $summary['written_off_loans']],
            ['Portfolio', 'Pipeline loans', $summary['pipeline_loans']],
            ['Portfolio', 'Principal disbursed', $money($summary['disbursed'])],
            ['Portfolio', 'Outstanding total', $money($summary['outstanding'])],
            ['Portfolio', 'Outstanding principal', $money($summary['outstanding_principal'])],
            ['Portfolio', 'Outstanding interest', $money($summary['outstanding_interest'])],
            ['Portfolio', 'Outstanding fees', $money($summary['outstanding_fee'])],
            ['Portfolio', 'Outstanding penalties', $money($summary['outstanding_penalty'])],
            ['Portfolio', 'Arrears', $money($summary['arrears'])],
            ['Portfolio', 'Collected total', $money($summary['collected'])],
            ['Portfolio', 'Collected principal', $money($summary['collected_principal'])],
            ['Portfolio', 'Collected interest', $money($summary['collected_interest'])],
            ['Portfolio', 'Collected fees', $money($summary['collected_fee'])],
            ['Portfolio', 'Collected penalties', $money($summary['collected_penalty'])],
            ['Aging', 'Current', $money($aging['current'])],
            ['Aging', '1-30 days', $money($aging['days_1_30'])],
            ['Aging', '31-60 days', $money($aging['days_31_60'])],
            ['Aging', '61-90 days', $money($aging['days_61_90'])],
            ['Aging', 'Over 90 days', $money($aging['days_over_90'])],
            ['Risk', 'Portfolio at risk', $money($risk['par']['amount'])],
            ['Risk', 'Portfolio at risk %', $percent($risk['par']['ratio'])],
            ['Risk', 'PAR 30', $money($risk['par30']['amount'])],
            ['Risk', 'PAR 30 %', $percent($risk['par30']['ratio'])],
            ['Risk', 'PAR 60', $money($risk['par60']['amount'])],
            ['Risk', 'PAR 60 %', $percent($risk['par60']['ratio'])],
            ['Risk', 'PAR 90', $money($risk['par90']['amount'])],
            ['Risk', 'PAR 90 %', $percent($risk['par90']['ratio'])],
            ['Collections', 'Due today', $money($collections['due_today'])],
            ['Collections', 'Due this week', $money($collections['due_this_week'])],
            ['Collections', 'Overdue', $money($collections['overdue'])],
        ];

        return $this->csv('portfolio-summary.csv', ['Section', 'Metric', 'Value'], $rows);
    }

    private function exportLoans(): StreamedResponse
    {
        return $this->exportLoanRows(
            'loan-report.csv',
            $this->reporting->loanRows(),
        );
    }

    /**
     * Export loans carrying an overdue balance, largest arrears first.
     */
    private function exportArrears(): StreamedResponse
    {
        $loans = $this->reporting->loanRows()
            ->filter(fn (array $loan): bool => $loan['arrears'] > 0)
            ->sortByDesc('arrears')
            ->values();

        return $this->exportLoanRows('arrears-report.csv', $loans);
    }

    /**
     * Stream the shared per-loan columns for the given loan rows.
     *
     * @param  Collection<int, array<string, mixed>>  $loans
     */
    private function exportLoanRows(string $filename, Collection $loans): StreamedResponse
    {
        $money = $this->money(...);

        $rows = $loans->map(fn (array $loan): array => [
            $loan['id'],
            $loan['borrower'],
            $loan['product'],
            $money($loan['principal']),
            $loan['status_label'],
            $loan['disbursed_at'],
            $money($loan['paid']),
            $money($loan['outstanding']),
            $money($loan['outstanding_principal']),
            $money($loan['outstanding_interest']),
            $money($loan['outstanding_fee']),
            $money($loan['outstanding_penalty']),
            $money($loan['arrears']),
            $loan['days_past_due'],
        ]);

        return $this->csv($filename, [
            'Loan',
            'Borrower',
            'Product',
            'Principal',
            'Status',
            'Disbursed',
            'Paid',
            'Outstanding',
            'Principal outstanding',
            'Interest outstanding',
            'Fees outstanding',
            'Penalties outstanding',
            'Arrears',
            'Days past due',
        ], $rows);
    }

    private function exportCollections(): StreamedResponse
    {
        $collections = $this->reporting->collections();
        $money = $this->money(...);

        $rows = collect($collections['overdue_installments'])
            ->merge($collections['upcoming'])
            ->sortBy('due_date')
            ->values()
            ->map(fn (array $installment): array => [
                $installment['borrower'],
                $installment['loan_id'],
                $installment['sequence'],
                $installment['due_date'],
                $money($installment['amount']),
                $installment['days_past_due'],
                $installment['days_past_due'] > 0 ? 'Overdue' : 'Due',
            ]);

        return $this->csv('collections.csv', [
            'Borrower',
            'Loan',
            'Installment',
            'Due date',
            'Amount',
            'Days past due',
            'Status',
        ], $rows);
    }

    /**
     * Stream the given rows as a CSV download.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return $this->csvExporter->stream($filename, collect([$headers])->concat($rows));
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
