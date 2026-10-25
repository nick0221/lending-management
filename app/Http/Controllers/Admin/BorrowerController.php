<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBorrowerRequest;
use App\Http\Requests\Admin\UpdateBorrowerKycRequest;
use App\Http\Requests\Admin\UpdateBorrowerRequest;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BorrowerController extends Controller
{
    /**
     * Display a listing of the borrowers.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Borrower::class);

        $actor = $request->user();
        $status = KycStatus::tryFrom($request->string('kyc_status')->toString());
        $search = trim($request->string('search')->toString());

        $applySearch = fn ($query) => $query->whereAny(
            ['name', 'email', 'phone'],
            'like',
            "%{$search}%",
        );

        $counts = Borrower::query()
            ->when($search !== '', $applySearch)
            ->selectRaw('kyc_status, count(*) as aggregate')
            ->groupBy('kyc_status')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->kyc_status => (int) $row->aggregate,
            ]);

        $borrowers = Borrower::query()
            ->with('user')
            ->when($status, fn ($query) => $query->where('kyc_status', $status))
            ->when($search !== '', $applySearch)
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Borrower $borrower): array => [
                ...$this->toPayload($borrower),
                'can' => [
                    'view' => $actor->can('view', $borrower),
                    'update' => $actor->can('update', $borrower),
                    'delete' => $actor->can('delete', $borrower),
                ],
            ]);

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
     * Display the given borrower with their loans and recent payments.
     */
    public function show(Request $request, Borrower $borrower, ReportingService $reportingService): Response
    {
        Gate::authorize('view', $borrower);

        $actor = $request->user();
        $borrower->load('user');

        $activity = [];

        if ($actor->can('viewAny', ActivityLog::class)) {
            $loanIds = $borrower->loans()->pluck('id');

            $activity = ActivityLog::query()
                ->with('user:id,name')
                ->where(function ($query) use ($borrower, $loanIds): void {
                    $query
                        ->where(fn ($query) => $query
                            ->where('subject_type', Borrower::class)
                            ->where('subject_id', $borrower->id))
                        ->orWhere(fn ($query) => $query
                            ->where('subject_type', Loan::class)
                            ->whereIn('subject_id', $loanIds));
                })
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
