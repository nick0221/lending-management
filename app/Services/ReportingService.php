<?php

namespace App\Services;

use App\Enums\CollectionOutcome;
use App\Enums\LoanEventType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Borrower;
use App\Models\CollectionContact;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
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

    private ?CarbonInterface $from = null;

    private ?CarbonInterface $to = null;

    /**
     * Limit every report to loans applied inside the given period.
     */
    public function forPeriod(?CarbonInterface $from, ?CarbonInterface $to): static
    {
        $this->from = $from?->startOfDay();
        $this->to = $to?->endOfDay();
        $this->loanRows = null;

        return $this;
    }

    /**
     * Per-loan report rows, built once per request.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function loanRows(): Collection
    {
        return $this->loanRows ??= Loan::query()
            ->with(['borrower:id,name', 'loanProduct:id,name', 'installments.allocations', 'payments'])
            ->when(
                $this->from !== null,
                fn ($query) => $query->whereDate('applied_at', '>=', $this->from->toDateString()),
            )
            ->when(
                $this->to !== null,
                fn ($query) => $query->whereDate('applied_at', '<=', $this->to->toDateString()),
            )
            ->orderBy('id')
            ->get()
            ->map(fn (Loan $loan): array => $this->loanRow($loan));
    }

    /**
     * Collection activity and promises grouped by the officer who logged it.
     *
     * The period narrows contacts by the date they were logged, not by the
     * loan application date used by the rest of the reports.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function officerPerformance(): Collection
    {
        return CollectionContact::query()
            ->with('user:id,name')
            ->when(
                $this->from !== null,
                fn ($query) => $query->whereDate('contacted_at', '>=', $this->from->toDateString()),
            )
            ->when(
                $this->to !== null,
                fn ($query) => $query->whereDate('contacted_at', '<=', $this->to->toDateString()),
            )
            ->orderBy('contacted_at')
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $contacts): array {
                $promises = $contacts->filter(
                    fn (CollectionContact $contact): bool => $contact->outcome->isPromise(),
                );

                return [
                    'user_id' => $contacts->first()->user_id,
                    'officer' => $contacts->first()->user?->name,
                    'contacts' => $contacts->count(),
                    'loans' => $contacts->pluck('loan_id')->unique()->count(),
                    'promises' => $promises->count(),
                    'promised_amount' => round((float) $promises->sum('promised_amount'), 2),
                    'paid' => $contacts
                        ->filter(fn (CollectionContact $contact): bool => $contact->outcome === CollectionOutcome::Paid)
                        ->count(),
                    'no_answer' => $contacts
                        ->filter(fn (CollectionContact $contact): bool => $contact->outcome === CollectionOutcome::NoAnswer)
                        ->count(),
                ];
            })
            ->sortByDesc('contacts')
            ->values();
    }

    /**
     * Payments received per month, newest month last. Reversed payments are
     * excluded. Without an explicit period the trend covers the last six
     * months including the current one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collectionsTrend(int $months = 6): array
    {
        $end = $this->to ?? Date::now()->endOfDay();
        $start = $this->from ?? $end->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $payments = Payment::query()
            ->whereNull('reversed_at')
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString())
            ->get(['amount', 'payment_date']);

        $buckets = [];

        for (
            $cursor = $start->copy()->startOfMonth();
            $cursor->lte($end);
            $cursor = $cursor->copy()->addMonthNoOverflow()
        ) {
            $key = $cursor->format('Y-m');

            $buckets[$key] = [
                'month' => $key,
                'label' => $cursor->format('M Y'),
                'amount' => 0.0,
                'count' => 0,
            ];
        }

        foreach ($payments as $payment) {
            $key = $payment->payment_date->format('Y-m');

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['amount'] += (float) $payment->amount;
            $buckets[$key]['count']++;
        }

        return collect($buckets)
            ->map(fn (array $bucket): array => [
                ...$bucket,
                'amount' => round($bucket['amount'], 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Money collected and disbursed per month, newest month last. Reversed
     * payments are excluded. Without an explicit period the trend covers the
     * last six months including the current one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cashFlowTrend(int $months = 6): array
    {
        $end = $this->to ?? Date::now()->endOfDay();
        $start = $this->from ?? $end->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $payments = Payment::query()
            ->whereNull('reversed_at')
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString())
            ->get(['amount', 'payment_date']);

        $disbursements = Disbursement::query()
            ->whereDate('disbursed_at', '>=', $start->toDateString())
            ->whereDate('disbursed_at', '<=', $end->toDateString())
            ->get(['amount', 'disbursed_at']);

        $buckets = [];

        for (
            $cursor = $start->copy()->startOfMonth();
            $cursor->lte($end);
            $cursor = $cursor->copy()->addMonthNoOverflow()
        ) {
            $key = $cursor->format('Y-m');

            $buckets[$key] = [
                'month' => $key,
                'label' => $cursor->format('M Y'),
                'collected' => 0.0,
                'payments' => 0,
                'disbursed' => 0.0,
                'payouts' => 0,
            ];
        }

        foreach ($payments as $payment) {
            $key = $payment->payment_date->format('Y-m');

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['collected'] += (float) $payment->amount;
            $buckets[$key]['payments']++;
        }

        foreach ($disbursements as $disbursement) {
            $key = $disbursement->disbursed_at->format('Y-m');

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['disbursed'] += (float) $disbursement->amount;
            $buckets[$key]['payouts']++;
        }

        return collect($buckets)
            ->map(function (array $bucket): array {
                $bucket['collected'] = round($bucket['collected'], 2);
                $bucket['disbursed'] = round($bucket['disbursed'], 2);
                $bucket['net'] = round($bucket['collected'] - $bucket['disbursed'], 2);

                return $bucket;
            })
            ->values()
            ->all();
    }

    /**
     * Payments received per method inside the report period.
     *
     * The period narrows payments by the date they were received rather than
     * the loan application date used by the rest of the reports.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function paymentMethodPerformance(): Collection
    {
        return Payment::query()
            ->when(
                $this->from !== null,
                fn ($query) => $query->whereDate('payment_date', '>=', $this->from->toDateString()),
            )
            ->when(
                $this->to !== null,
                fn ($query) => $query->whereDate('payment_date', '<=', $this->to->toDateString()),
            )
            ->get(['amount', 'method', 'reversed_at'])
            ->groupBy(fn (Payment $payment): string => $payment->method->value)
            ->map(function (Collection $payments, string $method): array {
                $completed = $payments->whereNull('reversed_at');
                $reversed = $payments->whereNotNull('reversed_at');

                return [
                    'method' => $method,
                    'method_label' => PaymentMethod::from($method)->label(),
                    'completed_count' => $completed->count(),
                    'completed_amount' => round((float) $completed->sum('amount'), 2),
                    'reversed_count' => $reversed->count(),
                    'reversed_amount' => round((float) $reversed->sum('amount'), 2),
                ];
            })
            ->sortByDesc('completed_amount')
            ->values();
    }

    /**
     * Per-borrower lending and collection performance, worst arrears first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function borrowerPerformance(): Collection
    {
        return $this->loanRows()
            ->groupBy('borrower_id')
            ->map(function (Collection $rows): array {
                $open = $rows->where('is_open', true);
                $disbursed = round(
                    $rows->filter(fn (array $loan): bool => $loan['disbursed_at'] !== null)
                        ->sum('principal'),
                    2,
                );

                return [
                    'borrower_id' => $rows->first()['borrower_id'],
                    'borrower' => $rows->first()['borrower'],
                    'loans' => $rows->count(),
                    'open_loans' => $open->count(),
                    'disbursed' => $disbursed,
                    'collected' => round($rows->sum('paid'), 2),
                    'outstanding' => round($open->sum('outstanding'), 2),
                    'arrears' => round($open->sum('arrears'), 2),
                    'days_past_due' => (int) $open->max('days_past_due'),
                ];
            })
            ->sortByDesc('arrears')
            ->values();
    }

    /**
     * Per-product lending and collection performance.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function productPerformance(): Collection
    {
        return $this->loanRows()
            ->groupBy('product_id')
            ->map(function (Collection $rows): array {
                $open = $rows->where('is_open', true);
                $disbursed = round(
                    $rows->filter(fn (array $loan): bool => $loan['disbursed_at'] !== null)
                        ->sum('principal'),
                    2,
                );
                $collected = round($rows->sum('paid'), 2);

                return [
                    'product_id' => $rows->first()['product_id'],
                    'product' => $rows->first()['product'],
                    'loans' => $rows->count(),
                    'open_loans' => $open->count(),
                    'disbursed' => $disbursed,
                    'collected' => $collected,
                    'outstanding' => round($open->sum('outstanding'), 2),
                    'arrears' => round($open->sum('arrears'), 2),
                    'collection_rate' => $disbursed > 0
                        ? round($collected / $disbursed * 100, 2)
                        : 0.0,
                ];
            })
            ->sortByDesc('disbursed')
            ->values();
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
        $statusCounts = $rows->countBy('status');
        $loanIds = $rows->pluck('id')->all();

        $collected = DB::table('loan_payment_allocations')
            ->join('payments', 'payments.id', '=', 'loan_payment_allocations.payment_id')
            ->whereIn('payments.loan_id', $loanIds)
            ->whereNull('payments.reversed_at')
            ->selectRaw('coalesce(sum(principal_allocated), 0) as principal')
            ->selectRaw('coalesce(sum(interest_allocated), 0) as interest')
            ->selectRaw('coalesce(sum(fee_allocated), 0) as fee')
            ->selectRaw('coalesce(sum(penalty_allocated), 0) as penalty')
            ->first();

        return [
            'borrowers' => $this->from === null && $this->to === null
                ? Borrower::query()->count()
                : $rows->pluck('borrower_id')->unique()->count(),
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
            'disbursed' => round((float) Disbursement::query()->whereIn('loan_id', $loanIds)->sum('amount'), 2),
            'outstanding' => round($open->sum('outstanding'), 2),
            'outstanding_principal' => round($open->sum('outstanding_principal'), 2),
            'outstanding_interest' => round($open->sum('outstanding_interest'), 2),
            'outstanding_fee' => round($open->sum('outstanding_fee'), 2),
            'outstanding_penalty' => round($open->sum('outstanding_penalty'), 2),
            'arrears' => round($open->sum('arrears'), 2),
            'collected' => round((float) $rows->sum('paid'), 2),
            'collected_principal' => round((float) ($collected->principal ?? 0), 2),
            'collected_interest' => round((float) ($collected->interest ?? 0), 2),
            'collected_fee' => round((float) ($collected->fee ?? 0), 2),
            'collected_penalty' => round((float) ($collected->penalty ?? 0), 2),
        ];
    }

    /**
     * Loans written off inside the report period, with the balance that was
     * cleared and the officer who made the call.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function writeOffs(): Collection
    {
        return Loan::query()
            ->with([
                'borrower:id,name',
                'loanProduct:id,name',
                'installments.allocations',
                'payments',
                'events.user:id,name',
            ])
            ->where('status', LoanStatus::WrittenOff->value)
            ->when(
                $this->from !== null,
                fn ($query) => $query->whereDate('applied_at', '>=', $this->from->toDateString()),
            )
            ->when(
                $this->to !== null,
                fn ($query) => $query->whereDate('applied_at', '<=', $this->to->toDateString()),
            )
            ->get()
            ->map(function (Loan $loan): array {
                $event = $loan->events
                    ->where('action', LoanEventType::WrittenOff)
                    ->sortByDesc('created_at')
                    ->first();

                $writtenOff = $loan->installments->sum(
                    fn (LoanInstallment $installment): float => round(
                        $installment->outstandingDue() + $installment->outstandingPenalty(),
                        2,
                    ),
                );

                return [
                    'id' => $loan->id,
                    'borrower_id' => $loan->borrower_id,
                    'borrower' => $loan->borrower?->name,
                    'product' => $loan->loanProduct?->name,
                    'principal' => round((float) $loan->principal, 2),
                    'paid' => round((float) $loan->payments->whereNull('reversed_at')->sum('amount'), 2),
                    'written_off' => round((float) $writtenOff, 2),
                    'written_off_at' => $event?->created_at?->toDateString(),
                    'written_off_by' => $event?->user?->name,
                ];
            })
            ->sortByDesc(fn (array $row): string => $row['written_off_at'] ?? '')
            ->values();
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
            ->whereHas('loan', fn ($query) => $query
                ->whereIn('status', $this->openStatusValues())
                ->when(
                    $this->from !== null,
                    fn ($loanQuery) => $loanQuery->whereDate('applied_at', '>=', $this->from->toDateString()),
                )
                ->when(
                    $this->to !== null,
                    fn ($loanQuery) => $loanQuery->whereDate('applied_at', '<=', $this->to->toDateString()),
                ))
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
            ->whereNull('reversed_at')
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
     * The most recent payouts recorded across the portfolio or one borrower.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentDisbursements(int $limit = 5, ?int $borrowerId = null): Collection
    {
        return Disbursement::query()
            ->with(['loan.borrower:id,name', 'recordedBy:id,name'])
            ->when(
                $borrowerId !== null,
                fn ($query) => $query->whereHas('loan', fn ($loan) => $loan->where('borrower_id', $borrowerId)),
            )
            ->orderByDesc('disbursed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Disbursement $disbursement): array => [
                'id' => $disbursement->id,
                'loan_id' => $disbursement->loan_id,
                'borrower' => $disbursement->loan?->borrower?->name,
                'amount' => round((float) $disbursement->amount, 2),
                'method' => $disbursement->method->value,
                'method_label' => $disbursement->method->label(),
                'reference' => $disbursement->reference,
                'disbursed_at' => $disbursement->disbursed_at->toDateString(),
                'recorded_by' => $disbursement->recordedBy?->name,
            ]);
    }

    /**
     * A single borrower's loans, arrears and next amounts due.
     *
     * @return array<string, mixed>
     */
    public function borrowerSummary(Borrower $borrower, int $paymentLimit = 5): array
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
            'upcoming_count' => count($upcoming),
            'loans' => $rows->values()->all(),
            'payments' => $this->recentPayments($paymentLimit, $borrower->id)->all(),
            'payment_count' => $loans->sum(
                fn (Loan $loan): int => $loan->payments->whereNull('reversed_at')->count(),
            ),
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
            'borrower_id' => $loan->borrower_id,
            'borrower' => $loan->borrower?->name,
            'product_id' => $loan->loan_product_id,
            'product' => $loan->loanProduct?->name,
            'principal' => round((float) $loan->principal, 2),
            'status' => $loan->status->value,
            'status_label' => $loan->status->label(),
            'is_open' => in_array($loan->status, self::OPEN_STATUSES, true),
            'disbursed_at' => $loan->disbursed_at?->toDateString(),
            'paid' => round((float) $loan->payments->whereNull('reversed_at')->sum('amount'), 2),
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
