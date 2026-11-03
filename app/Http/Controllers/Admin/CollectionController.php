<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CollectionOutcome;
use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCollectionOfficerRequest;
use App\Http\Requests\Admin\LogCollectionContactRequest;
use App\Models\CollectionContact;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * Loan statuses that can still be collected.
     *
     * @var array<int, LoanStatus>
     */
    private const OPEN_STATUSES = [LoanStatus::Active, LoanStatus::Defaulted];

    /**
     * The queue tabs, in display order.
     *
     * @var array<int, string>
     */
    private const TABS = ['all', 'unassigned', 'promised', 'follow_up'];

    /**
     * Aging buckets keyed by their lower and upper bound in days.
     *
     * @var array<string, array{0: int, 1: int|null}>
     */
    private const BUCKETS = [
        '1_30' => [1, 30],
        '31_60' => [31, 60],
        '61_90' => [61, 90],
        'over_90' => [91, null],
    ];

    /**
     * Log a collection contact against the given loan.
     */
    public function store(LogCollectionContactRequest $request, Loan $loan): RedirectResponse
    {
        $validated = $request->validated();
        $outcome = CollectionOutcome::from($validated['outcome']);
        $promisedAt = $outcome->isPromise() ? $validated['promised_at'] : null;

        $loan->collectionContacts()->create([
            'user_id' => $request->user()->id,
            'channel' => $validated['channel'],
            'outcome' => $validated['outcome'],
            'contacted_at' => $validated['contacted_at'] ?? now(),
            'notes' => $validated['notes'] ?? null,
            'promised_amount' => $outcome->isPromise() ? $validated['promised_amount'] : null,
            'promised_at' => $promisedAt,
            'follow_up_at' => $validated['follow_up_at'] ?? $promisedAt,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Collection contact logged.'),
        ]);

        return back();
    }

    /**
     * Show the overdue loans that need to be worked.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Loan::class);

        $actor = $request->user();
        $tab = $request->string('tab')->toString();
        $tab = in_array($tab, self::TABS, true) ? $tab : 'all';
        $officerId = $request->integer('officer');
        $officerId = $officerId > 0 ? $officerId : null;
        $bucket = $request->string('bucket')->toString();
        $bucket = array_key_exists($bucket, self::BUCKETS) ? $bucket : null;
        $search = trim($request->string('search')->toString());
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['arrears', 'days_past_due'], true) ? $sort : 'arrears';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $applySearch = function (Builder $query) use ($search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereHas('borrower', fn (Builder $borrower) => $borrower
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));

                if (ctype_digit($search)) {
                    $query->orWhereKey((int) $search);
                }
            });
        };

        $base = Loan::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('days_past_due', '>', 0)
            ->when($officerId !== null, fn (Builder $query) => $query->where('collection_officer_id', $officerId))
            ->when($bucket !== null, fn (Builder $query) => $this->applyBucket($query, $bucket))
            ->when($search !== '', fn (Builder $query) => $applySearch($query));

        $counts = [
            'all' => (clone $base)->count(),
            'unassigned' => (clone $base)->whereNull('collection_officer_id')->count(),
            'promised' => (clone $base)
                ->whereHas('collectionContacts', fn (Builder $query) => $query
                    ->promises()
                    ->whereDate('promised_at', '>=', today()))
                ->count(),
            'follow_up' => (clone $base)
                ->whereHas('collectionContacts', fn (Builder $query) => $query
                    ->whereDate('follow_up_at', '<=', today()))
                ->count(),
        ];

        $loans = $base
            ->with([
                'borrower:id,name,phone',
                'loanProduct:id,name',
                'collectionOfficer:id,name',
                'collectionContacts.user:id,name',
                'installments.allocations',
            ])
            ->when($tab === 'unassigned', fn (Builder $query) => $query->whereNull('collection_officer_id'))
            ->when($tab === 'promised', fn (Builder $query) => $query
                ->whereHas('collectionContacts', fn (Builder $query) => $query
                    ->promises()
                    ->whereDate('promised_at', '>=', today())))
            ->when($tab === 'follow_up', fn (Builder $query) => $query
                ->whereHas('collectionContacts', fn (Builder $query) => $query
                    ->whereDate('follow_up_at', '<=', today())))
            ->when($sort === 'arrears', fn (Builder $query) => $query->orderByArrears($direction))
            ->when($sort === 'days_past_due', fn (Builder $query) => $query->orderBy('days_past_due', $direction))
            ->orderByDesc('loans.id')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Loan $loan): array => $this->toPayload($loan, $actor));

        return Inertia::render('admin/collections/index', [
            'loans' => $loans,
            'counts' => $counts,
            'summary' => [
                'loans' => $counts['all'],
                'arrears' => number_format($this->arrearsTotal($base), 2, '.', ''),
                'promised' => $counts['promised'],
                'unassigned' => $counts['unassigned'],
            ],
            'officers' => $this->officerOptions(),
            'buckets' => [
                ['value' => 'all', 'label' => 'All aging'],
                ['value' => '1_30', 'label' => '1–30 days'],
                ['value' => '31_60', 'label' => '31–60 days'],
                ['value' => '61_90', 'label' => '61–90 days'],
                ['value' => 'over_90', 'label' => '90+ days'],
            ],
            'currentTab' => $tab,
            'currentOfficer' => $officerId,
            'currentBucket' => $bucket,
            'currentSort' => $sort,
            'currentDirection' => $direction,
            'search' => $search,
        ]);
    }

    /**
     * Put the loan in the given collector's queue, or clear the assignment.
     */
    public function assignOfficer(AssignCollectionOfficerRequest $request, Loan $loan): RedirectResponse
    {
        $loan->update(['collection_officer_id' => $request->validated('officer_id')]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Collection officer updated.'),
        ]);

        return back();
    }

    /**
     * Apply an aging bucket to the queue query.
     */
    private function applyBucket(Builder $query, string $bucket): void
    {
        [$from, $to] = self::BUCKETS[$bucket];

        $query->where('days_past_due', '>=', $from)
            ->when($to !== null, fn (Builder $query) => $query->where('days_past_due', '<=', $to));
    }

    /**
     * Sum the overdue outstanding balance across the filtered queue.
     */
    private function arrearsTotal(Builder $query): float
    {
        $arrears = <<<'SQL'
            COALESCE((
                SELECT SUM(
                    (installments.principal_due + installments.interest_due + installments.fee_due + installments.penalty_accrued)
                    - COALESCE(allocated.total, 0)
                )
                FROM loan_installments AS installments
                LEFT JOIN (
                    SELECT installment_id,
                           SUM(principal_allocated + interest_allocated + fee_allocated + penalty_allocated) AS total
                    FROM loan_payment_allocations
                    GROUP BY installment_id
                ) AS allocated ON allocated.installment_id = installments.id
                WHERE installments.loan_id = loans.id
                  AND installments.due_date < ?
            ), 0)
            SQL;

        return round((float) (clone $query)
            ->selectRaw("COALESCE(SUM({$arrears}), 0) AS aggregate", [today()->toDateString()])
            ->value('aggregate'), 2);
    }

    /**
     * Transform one queued loan into the payload shared with the client.
     *
     * @return array<string, mixed>
     */
    private function toPayload(Loan $loan, User $actor): array
    {
        $contacts = $loan->collectionContacts;
        $lastContact = $contacts->first();
        $promise = $contacts->first(fn (CollectionContact $contact): bool => $contact->isOpenPromise());
        $missedPromise = $promise === null
            ? $contacts->first(fn (CollectionContact $contact): bool => $contact->outcome->isPromise()
                && $contact->promised_at !== null
                && $contact->promised_at->lt(today()))
            : null;
        $followUps = $contacts
            ->pluck('follow_up_at')
            ->filter(fn (?CarbonInterface $date): bool => $date !== null);
        $nextFollowUp = $followUps
            ->filter(fn (CarbonInterface $date): bool => $date->gte(today()))
            ->sort()
            ->first()
            ?? $followUps
                ->filter(fn (CarbonInterface $date): bool => $date->lt(today()))
                ->sortDesc()
                ->first();

        $outstanding = round($loan->installments->sum(
            fn (LoanInstallment $installment): float => $installment->outstandingTotal(),
        ), 2);
        $arrears = round($loan->installments->sum(
            fn (LoanInstallment $installment): float => $installment->isOverdue() ? $installment->outstandingTotal() : 0.0,
        ), 2);

        $describedPromise = $promise ?? $missedPromise;

        return [
            'id' => $loan->id,
            'borrower_id' => $loan->borrower_id,
            'borrower_name' => $loan->borrower->name,
            'borrower_phone' => $loan->borrower->phone,
            'loan_product_name' => $loan->loanProduct->name,
            'status' => $loan->status->value,
            'status_label' => $loan->status->label(),
            'days_past_due' => $loan->days_past_due,
            'outstanding' => number_format($outstanding, 2, '.', ''),
            'arrears' => number_format($arrears, 2, '.', ''),
            'collection_officer_id' => $loan->collection_officer_id,
            'collection_officer' => $loan->collectionOfficer?->name,
            'last_contact' => $lastContact === null ? null : [
                'channel_label' => $lastContact->channel->label(),
                'outcome' => $lastContact->outcome->value,
                'outcome_label' => $lastContact->outcome->label(),
                'contacted_at' => $lastContact->contacted_at?->toIso8601String(),
            ],
            'promise' => $describedPromise === null ? null : [
                'amount' => $describedPromise->promised_amount,
                'promised_at' => $describedPromise->promised_at?->toDateString(),
                'days_until' => $promise !== null
                    ? (int) today()->diffInDays($promise->promised_at, false)
                    : null,
                'missed' => $promise === null,
            ],
            'next_follow_up' => $nextFollowUp?->toDateString(),
            'follow_up_overdue' => $nextFollowUp !== null && $nextFollowUp->lt(today()),
            'can' => [
                'view' => $actor->can('view', $loan),
                'assign' => $actor->can('assignOfficer', $loan),
                'log_contact' => $actor->can('logContact', $loan),
            ],
        ];
    }

    /**
     * Map the staff who can hold a collection queue to select options.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function officerOptions(): array
    {
        return User::query()
            ->whereIn('role', [Role::SuperAdmin->value, Role::Admin->value])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])
            ->all();
    }
}
