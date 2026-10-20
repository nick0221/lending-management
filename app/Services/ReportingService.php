<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates portfolio, collections and borrower figures for the dashboards
 * and report exports. Every figure is returned as a raw number so the
 * controllers, pages and CSV exports all derive from the same calculation.
 */
class ReportingService
{
    /**
     * Statuses whose outstanding balance counts as a receivable.
     *
     * @var array<int, LoanStatus>
     */
    private const OPEN_STATUSES = [LoanStatus::Active, LoanStatus::Defaulted];

    /**
     * Statuses that still owe money to the lender. A schedule generated for a
     * loan that has not been disbursed is a plan, not a receivable.
     *
     * @var array<int, LoanStatus>
     */
    private const RECEIVABLE_STATUSES = [
        LoanStatus::Disbursed,
        LoanStatus::Active,
        LoanStatus::Defaulted,
    ];

    /**
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $loanRows = null;

    /**
     * Per-loan report rows, built once per request.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function loanRows(): Collection
    {
        return $this->loanRows ??= Loan::query()
            ->with(['borrower:id,name', 'loanProduct:id,name', 'installments.allocations', 'payments'])
            ->orderBy('id')
            ->get()
            ->map(fn (Loan $loan): array => $this->loanRow($loan));
    }

    /**
     * Headline portfolio figures.
     *
     * @return array<string, int|float>
     */
    public function portfolioSummary(): array
    {
        $rows = $this->loanRows();
        $open = $rows->where('is_open', true);

        $statusCounts = Loan::query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $collected = DB::table('loan_payment_allocations')
            ->selectRaw('coalesce(sum(principal_allocated), 0) as principal')
            ->selectRaw('coalesce(sum(interest_allocated), 0) as interest')
            ->selectRaw('coalesce(sum(fee_allocated), 0) as fee')
            ->selectRaw('coalesce(sum(penalty_allocated), 0) as penalty')
            ->first();

        return [
            'borrowers' => Borrower::query()->count(),
            'loans' => $rows->count(),
            'open_loans' => $open->count(),
            'active_loans' => (int) ($statusCounts[LoanStatus::Active->value] ?? 0),
            'defaulted_loans' => (int) ($statusCounts[LoanStatus::Defaulted->value] ?? 0),
            'closed_loans' => (int) ($statusCounts[LoanStatus::Closed->value] ?? 0),
            'written_off_loans' => (int) ($statusCounts[LoanStatus::WrittenOff->value] ?? 0),
            'pipeline_loans' => (int) (
                ($statusCounts[LoanStatus::Draft->value] ?? 0)
                + ($statusCounts[LoanStatus::Pending->value] ?? 0)
                + ($statusCounts[LoanStatus::Approved->value] ?? 0)
                + ($statusCounts[LoanStatus::Disbursed->value] ?? 0)
                + ($statusCounts[LoanStatus::Rejected->value] ?? 0)
            ),
            'disbursed' => round((float) Disbursement::query()->sum('amount'), 2),
            'outstanding' => round($open->sum('outstanding'), 2),
            'outstanding_principal' => round($open->sum('outstanding_principal'), 2),
            'outstanding_interest' => round($open->sum('outstanding_interest'), 2),
            'outstanding_fee' => round($open->sum('outstanding_fee'), 2),
            'outstanding_penalty' => round($open->sum('outstanding_penalty'), 2),
            'arrears' => round($open->sum('arrears'), 2),
            'collected' => round((float) Payment::query()->sum('amount'), 2),
            'collected_principal' => round((float) $collected->principal, 2),
            'collected_interest' => round((float) $collected->interest, 2),
            'collected_fee' => round((float) $collected->fee, 2),
            'collected_penalty' => round((float) $collected->penalty, 2),
        ];
    }

    /**
     * Outstanding balances grouped by how far past due they are.
     *
     * @return array<string, float>
     */
    public function agingBuckets(): array
    {
        $aging = $this->emptyAging();

        foreach ($this->loanRows()->where('is_open', true) as $row) {
            foreach ($row['aging'] as $bucket => $amount) {
                $aging[$bucket] = round($aging[$bucket] + $amount, 2);
            }
        }

        return $aging;
    }

    /**
     * Portfolio at risk, expressed as an amount and a share of outstanding.
     *
     * @return array<string, mixed>
     */
    public function portfolioAtRisk(): array
    {
        $open = $this->loanRows()->where('is_open', true);
        $outstanding = round($open->sum('outstanding'), 2);

        $atRisk = fn (int $days): float => round(
            $open->filter(fn (array $row): bool => $row['days_past_due'] > $days)->sum('outstanding'),
            2,
        );

        return [
            'outstanding' => $outstanding,
            'par' => $this->riskBucket(1, $atRisk(0), $outstanding),
            'par30' => $this->riskBucket(30, $atRisk(30), $outstanding),
            'par60' => $this->riskBucket(60, $atRisk(60), $outstanding),
            'par90' => $this->riskBucket(90, $atRisk(90), $outstanding),
        ];
    }

    /**
     * Collections due today, over the next week and currently overdue.
     *
     * @return array<string, mixed>
     */
    public function collections(?CarbonInterface $asOf = null): array
    {
        $asOf = ($asOf ?? today())->startOfDay();
        $weekEnd = $asOf->addDays(7);

        $installments = LoanInstallment::query()
            ->with(['loan.borrower:id,name'])
            ->whereHas('loan', fn ($query) => $query->whereIn('status', $this->openStatusValues()))
            ->whereDate('due_date', '<=', $weekEnd->toDateString())
            ->orderBy('due_date')
            ->orderBy('loan_id')
            ->get();

        $dueToday = 0.0;
        $dueThisWeek = 0.0;
        $overdue = 0.0;
        $upcoming = [];
        $overdueInstallments = [];

        foreach ($installments as $installment) {
            $amount = $installment->outstandingTotal();

            if ($amount <= 0) {
                continue;
            }

            $dueDate = $installment->due_date->startOfDay();

            $row = [
                'loan_id' => $installment->loan_id,
                'borrower' => $installment->loan?->borrower?->name,
                'sequence' => $installment->sequence,
                'due_date' => $dueDate->toDateString(),
                'amount' => round($amount, 2),
                'days_past_due' => $installment->daysPastDue(),
            ];

            if ($dueDate->lessThan($asOf)) {
                $overdue = round($overdue + $amount, 2);
                $overdueInstallments[] = $row;

                continue;
            }

            $upcoming[] = $row;
            $dueThisWeek = round($dueThisWeek + $amount, 2);

            if ($dueDate->equalTo($asOf)) {
                $dueToday = round($dueToday + $amount, 2);
            }
        }

        return [
            'as_of' => $asOf->toDateString(),
            'due_today' => $dueToday,
            'due_this_week' => $dueThisWeek,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'overdue_installments' => $overdueInstallments,
        ];
    }

    /**
     * The most recent payments recorded across the portfolio or one borrower.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentPayments(int $limit = 5, ?int $borrowerId = null): Collection
    {
        return Payment::query()
            ->with(['loan.borrower:id,name', 'recordedBy:id,name'])
            ->when(
                $borrowerId !== null,
                fn ($query) => $query->whereHas('loan', fn ($loan) => $loan->where('borrower_id', $borrowerId)),
            )
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'loan_id' => $payment->loan_id,
                'borrower' => $payment->loan?->borrower?->name,
                'amount' => round((float) $payment->amount, 2),
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'reference' => $payment->reference,
                'payment_date' => $payment->payment_date->toDateString(),
                'recorded_by' => $payment->recordedBy?->name,
            ]);
    }

    /**
     * A single borrower's loans, arrears and next amounts due.
     *
     * @return array<string, mixed>
     */
    public function borrowerSummary(Borrower $borrower): array
    {
        $loans = $borrower->loans()
            ->with(['loanProduct:id,name', 'installments.allocations', 'payments'])
            ->orderBy('id')
            ->get();

        $rows = $loans->map(fn (Loan $loan): array => $this->loanRow($loan));
        $open = $rows->where('is_open', true);

        $upcoming = [];

        foreach ($loans as $loan) {
            if (! in_array($loan->status, self::OPEN_STATUSES, true)) {
                continue;
            }

            foreach ($loan->installments as $installment) {
                $amount = $installment->outstandingTotal();

                if ($amount <= 0) {
                    continue;
                }

                $upcoming[] = [
                    'loan_id' => $loan->id,
                    'sequence' => $installment->sequence,
                    'due_date' => $installment->due_date->toDateString(),
                    'amount' => round($amount, 2),
                    'days_past_due' => $installment->daysPastDue(),
                ];
            }
        }

        usort($upcoming, fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']));

        return [
            'borrower' => [
                'id' => $borrower->id,
                'name' => $borrower->name,
                'kyc_status' => $borrower->kyc_status->value,
                'kyc_status_label' => $borrower->kyc_status->label(),
            ],
            'loan_count' => $loans->count(),
            'open_loan_count' => $open->count(),
            'outstanding' => round($open->sum('outstanding'), 2),
            'arrears' => round($open->sum('arrears'), 2),
            'next_due' => $upcoming[0] ?? null,
            'upcoming' => array_slice($upcoming, 0, 5),
            'loans' => $rows->values()->all(),
            'payments' => $this->recentPayments(5, $borrower->id)->all(),
        ];
    }

    /**
     * Build one report row with outstanding, arrears and aging.
     *
     * @return array<string, mixed>
     */
    private function loanRow(Loan $loan): array
    {
        $outstanding = 0.0;
        $principal = 0.0;
        $interest = 0.0;
        $fee = 0.0;
        $penalty = 0.0;
        $arrears = 0.0;
        $daysPastDue = 0;
        $aging = $this->emptyAging();

        if (in_array($loan->status, self::RECEIVABLE_STATUSES, true)) {
            foreach ($loan->installments as $installment) {
                $penaltyOutstanding = $installment->outstandingPenalty();
                $total = round($installment->outstandingDue() + $penaltyOutstanding, 2);

                $outstanding = round($outstanding + $total, 2);
                $principal = round($principal + $installment->outstandingPrincipal(), 2);
                $interest = round($interest + $installment->outstandingInterest(), 2);
                $fee = round($fee + $installment->outstandingFee(), 2);
                $penalty = round($penalty + $penaltyOutstanding, 2);

                if ($total <= 0) {
                    continue;
                }

                if ($installment->isOverdue()) {
                    $days = $installment->daysPastDue();
                    $daysPastDue = max($daysPastDue, $days);
                    $arrears = round($arrears + $total, 2);
                    $bucket = $this->agingBucket($days);
                } else {
                    $bucket = 'current';
                }

                $aging[$bucket] = round($aging[$bucket] + $total, 2);
            }
        }

        return [
            'id' => $loan->id,
            'borrower' => $loan->borrower?->name,
            'product' => $loan->loanProduct?->name,
            'principal' => round((float) $loan->principal, 2),
            'status' => $loan->status->value,
            'status_label' => $loan->status->label(),
            'is_open' => in_array($loan->status, self::OPEN_STATUSES, true),
            'disbursed_at' => $loan->disbursed_at?->toDateString(),
            'paid' => round((float) $loan->payments->sum('amount'), 2),
            'outstanding' => $outstanding,
            'outstanding_principal' => $principal,
            'outstanding_interest' => $interest,
            'outstanding_fee' => $fee,
            'outstanding_penalty' => $penalty,
            'arrears' => $arrears,
            'days_past_due' => $daysPastDue,
            'aging' => $aging,
        ];
    }

    /**
     * @return array{days: int, amount: float, ratio: float}
     */
    private function riskBucket(int $days, float $amount, float $outstanding): array
    {
        return [
            'days' => $days,
            'amount' => $amount,
            'ratio' => $outstanding > 0 ? round($amount / $outstanding * 100, 2) : 0.0,
        ];
    }

    private function agingBucket(int $days): string
    {
        return match (true) {
            $days <= 30 => 'days_1_30',
            $days <= 60 => 'days_31_60',
            $days <= 90 => 'days_61_90',
            default => 'days_over_90',
        };
    }

    /**
     * @return array<string, float>
     */
    private function emptyAging(): array
    {
        return [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_over_90' => 0.0,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function openStatusValues(): array
    {
        return array_map(fn (LoanStatus $status): string => $status->value, self::OPEN_STATUSES);
    }
}
