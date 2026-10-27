<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBorrowerRequest;
use App\Http\Requests\Admin\UpdateBorrowerKycRequest;
use App\Http\Requests\Admin\UpdateBorrowerRequest;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\User;
use App\Services\CsvExporter;
use App\Services\ReportingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BorrowerController extends Controller
{
    public function __construct(private readonly CsvExporter $csvExporter) {}

    /**
     * Display a listing of the borrowers.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Borrower::class);

        $actor = $request->user();
        $status = $this->statusFilter($request);
        $search = $this->searchTerm($request);

        $counts = $this->applyFilters(Borrower::query(), null, $search)
            ->selectRaw('kyc_status, count(*) as aggregate')
            ->groupBy('kyc_status')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->kyc_status => (int) $row->aggregate,
            ]);

        $borrowers = $this->applyFilters(
            Borrower::query()->with(['user', 'loans.installments.allocations']),
            $status,
            $search,
        )
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(function (Borrower $borrower) use ($actor): array {
                $openLoans = $borrower->loans->filter(
                    fn (Loan $loan): bool => in_array(
                        $loan->status,
                        [LoanStatus::Active, LoanStatus::Defaulted],
                        true,
                    ),
                );

                return [
                    ...$this->toPayload($borrower),
                    'loan_count' => $borrower->loans->count(),
                    'open_loan_count' => $openLoans->count(),
                    'outstanding' => number_format(round(
                        $openLoans->sum(
                            fn (Loan $loan): float => $loan->installments->sum(
                                fn (LoanInstallment $installment): float => $installment->outstandingTotal(),
                            ),
                        ),
                        2,
                    ), 2, '.', ''),
                    'can' => [
                        'view' => $actor->can('view', $borrower),
                        'update' => $actor->can('update', $borrower),
                        'delete' => $actor->can('delete', $borrower),
                    ],
                ];
            });

        return Inertia::render('admin/borrowers/index', [
            'borrowers' => $borrowers,
            'kycStatuses' => $this->kycStatusOptions(),
            'currentKycStatus' => $status?->value,
            'search' => $search,
            'kycCounts' => [
                'all' => (int) $counts->sum(),
                ...collect(KycStatus::cases())
                    ->mapWithKeys(fn (KycStatus $case): array => [
                        $case->value => $counts->get($case->value, 0),
                    ])
                    ->all(),
            ],
        ]);
    }

    /**
     * Stream the borrower register for the current filters as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Borrower::class);

        return $this->csvExporter->stream(
            'borrowers-'.now()->format('Y-m-d').'.csv',
            $this->csvRows($this->statusFilter($request), $this->searchTerm($request)),
        );
    }

    /**
     * Display the given borrower with their loans and recent payments.
     */
    public function show(Request $request, Borrower $borrower, ReportingService $reportingService): Response
    {
        Gate::authorize('view', $borrower);

        $actor = $request->user();
        $borrower->load('user');

        $activity = [];
        $activityCount = 0;

        if ($actor->can('viewAny', ActivityLog::class)) {
            $loanIds = $borrower->loans()->pluck('id');

            $activityQuery = ActivityLog::query()
                ->where(function ($query) use ($borrower, $loanIds): void {
                    $query
                        ->where(fn ($query) => $query
                            ->where('subject_type', Borrower::class)
                            ->where('subject_id', $borrower->id))
                        ->orWhere(fn ($query) => $query
                            ->where('subject_type', Loan::class)
                            ->whereIn('subject_id', $loanIds));
                });

            $activityCount = (clone $activityQuery)->count();

            $activity = $activityQuery
                ->with('user:id,name')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (ActivityLog $log): array => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'event_label' => Str::headline($log->event),
                    'description' => $log->description,
                    'user_name' => $log->user?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->all();
        }

        return Inertia::render('admin/borrowers/show', [
            'borrower' => $this->detailPayload($borrower),
            'summary' => $reportingService->borrowerSummary($borrower, 10),
            'activity' => $activity,
            'activityCount' => $activityCount,
            'can' => [
                'update' => $actor->can('update', $borrower),
                'delete' => $actor->can('delete', $borrower),
                'viewActivity' => $actor->can('viewAny', ActivityLog::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new borrower.
     */
    public function create(): Response
    {
        Gate::authorize('create', Borrower::class);

        return Inertia::render('admin/borrowers/create', [
            'kycStatuses' => $this->kycStatusOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    /**
     * Store a newly created borrower.
     */
    public function store(StoreBorrowerRequest $request): RedirectResponse
    {
        Borrower::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Borrower created.')]);

        return to_route('admin.borrowers.index');
    }

    /**
     * Show the form for editing the given borrower.
     */
    public function edit(Borrower $borrower): Response
    {
        Gate::authorize('update', $borrower);

        return Inertia::render('admin/borrowers/edit', [
            'borrower' => $this->toPayload($borrower),
            'kycStatuses' => $this->kycStatusOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    /**
     * Update the given borrower.
     */
    public function update(UpdateBorrowerRequest $request, Borrower $borrower): RedirectResponse
    {
        $borrower->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Borrower updated.')]);

        return to_route('admin.borrowers.index');
    }

    /**
     * Update the KYC status of several borrowers at once.
     */
    public function updateKycStatus(UpdateBorrowerKycRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $status = KycStatus::from($validated['kyc_status']);
        $updated = 0;

        Borrower::query()
            ->whereKey($validated['borrowers'])
            ->get()
            ->each(function (Borrower $borrower) use ($status, &$updated): void {
                if ($borrower->kyc_status === $status) {
                    return;
                }

                $borrower->update(['kyc_status' => $status]);
                $updated++;
            });

        Inertia::flash('toast', [
            'type' => $updated > 0 ? 'success' : 'info',
            'message' => $updated > 0
                ? trans_choice(
                    'KYC status updated for :count borrower.|KYC status updated for :count borrowers.',
                    $updated,
                    ['count' => $updated],
                )
                : __('The selected borrowers already have that KYC status.'),
        ]);

        return back();
    }

    /**
     * Remove the given borrower.
     */
    public function destroy(Borrower $borrower): RedirectResponse
    {
        Gate::authorize('delete', $borrower);

        $borrower->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Borrower deleted.')]);

        return to_route('admin.borrowers.index');
    }

    /**
     * Resolve the KYC status filter from the request.
     */
    private function statusFilter(Request $request): ?KycStatus
    {
        return KycStatus::tryFrom($request->string('kyc_status')->toString());
    }

    /**
     * Read the trimmed search term from the request.
     */
    private function searchTerm(Request $request): string
    {
        return trim($request->string('search')->toString());
    }

    /**
     * Apply the register filters to a borrower query.
     */
    private function applyFilters(Builder $query, ?KycStatus $status, string $search): Builder
    {
        return $query
            ->when($status, fn (Builder $query) => $query->where('kyc_status', $status))
            ->when(
                $search !== '',
                fn (Builder $query) => $query->whereAny(
                    ['name', 'email', 'phone'],
                    'like',
                    "%{$search}%",
                ),
            );
    }

    /**
     * Build the CSV rows lazily so a long register streams in chunks.
     *
     * @return \Generator<int, array<int, string|int|null>>
     */
    private function csvRows(?KycStatus $status, string $search): \Generator
    {
        yield [
            'Name',
            'Phone',
            'Email',
            'National ID',
            'KYC status',
            'Portal account',
            'Joined',
        ];

        $borrowers = $this->applyFilters(Borrower::query()->with('user'), $status, $search)
            ->orderBy('name')
            ->lazy(500);

        foreach ($borrowers as $borrower) {
            yield [
                $borrower->name,
                $borrower->phone,
                $borrower->email,
                $borrower->national_id,
                $borrower->kyc_status->label(),
                $borrower->user?->email,
                $borrower->created_at?->toDateString(),
            ];
        }
    }

    /**
     * Add the linked account to the borrower payload.
     *
     * @return array<string, mixed>
     */
    private function detailPayload(Borrower $borrower): array
    {
        return [
            ...$this->toPayload($borrower),
            'user' => $borrower->user ? [
                'id' => $borrower->user->id,
                'name' => $borrower->user->name,
                'email' => $borrower->user->email,
            ] : null,
        ];
    }

    /**
     * Transform the borrower into the payload shared with the client.
     *
     * @return array<string, mixed>
     */
    private function toPayload(Borrower $borrower): array
    {
        return [
            'id' => $borrower->id,
            'user_id' => $borrower->user_id,
            'name' => $borrower->name,
            'national_id' => $borrower->national_id,
            'phone' => $borrower->phone,
            'email' => $borrower->email,
            'date_of_birth' => $borrower->date_of_birth?->toDateString(),
            'address' => $borrower->address,
            'kyc_status' => $borrower->kyc_status->value,
            'kyc_status_label' => $borrower->kyc_status->label(),
            'notes' => $borrower->notes,
            'notify_due_soon' => $borrower->notify_due_soon,
            'notify_overdue' => $borrower->notify_overdue,
            'notify_receipts' => $borrower->notify_receipts,
            'notify_statements' => $borrower->notify_statements,
            'notify_sms' => $borrower->notify_sms,
            'created_at' => $borrower->created_at?->toIso8601String(),
        ];
    }

    /**
     * Map the KYC statuses to select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function kycStatusOptions(): array
    {
        return array_map(
            fn (KycStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            KycStatus::cases(),
        );
    }

    /**
     * Map the linkable user accounts to select options.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'value' => $user->id,
                'label' => "{$user->name} ({$user->email})",
            ])
            ->all();
    }
}
