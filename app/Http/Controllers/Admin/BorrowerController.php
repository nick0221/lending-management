<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBorrowerRequest;
use App\Http\Requests\Admin\UpdateBorrowerRequest;
use App\Models\Borrower;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        $counts = Borrower::query()
            ->selectRaw('kyc_status, count(*) as aggregate')
            ->groupBy('kyc_status')
            ->get()
            ->mapWithKeys(fn (Borrower $borrower): array => [
                $borrower->kyc_status->value => (int) $borrower->aggregate,
            ]);

        $borrowers = Borrower::query()
            ->with('user')
            ->when($status, fn ($query) => $query->where('kyc_status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Borrower $borrower): array => [
                ...$this->toPayload($borrower),
                'can' => [
                    'update' => $actor->can('update', $borrower),
                    'delete' => $actor->can('delete', $borrower),
                ],
            ]);

        return Inertia::render('admin/borrowers/index', [
            'borrowers' => $borrowers,
            'kycStatuses' => $this->kycStatusOptions(),
            'currentKycStatus' => $status?->value,
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
