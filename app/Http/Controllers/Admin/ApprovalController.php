<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    /**
     * The status tabs, in display order.
     *
     * @var array<int, string>
     */
    private const TABS = ['pending', 'approved', 'rejected', 'all'];

    /**
     * Display the maker-checker queue.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ApprovalRequest::class);

        $actor = $request->user();
        $tab = $request->string('tab')->toString();
        $tab = in_array($tab, self::TABS, true) ? $tab : 'pending';
        $type = ApprovalType::tryFrom($request->string('type')->toString());
        $search = trim($request->string('search')->toString());

        $base = $this->baseQuery($type, $search);

        $counts = [
            'pending' => (clone $base)->where('status', ApprovalStatus::Pending)->count(),
            'approved' => (clone $base)->where('status', ApprovalStatus::Approved)->count(),
            'rejected' => (clone $base)->where('status', ApprovalStatus::Rejected)->count(),
            'all' => (clone $base)->count(),
        ];

        $approvals = $this->applyTab($base->with([
            'loan.borrower:id,name,phone',
            'requestedBy:id,name',
            'reviewedBy:id,name',
        ]), $tab)
            ->latest()
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (ApprovalRequest $approval): array => $this->toPayload($approval, $actor));

        return Inertia::render('admin/approvals/index', [
            'approvals' => $approvals,
            'counts' => $counts,
            'currentTab' => $tab,
            'currentType' => $type?->value,
            'types' => array_map(fn (ApprovalType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], ApprovalType::cases()),
            'search' => $search,
        ]);
    }

    /**
     * Approve the request and execute the action it guards.
     */
    public function approve(
        Request $request,
        ApprovalRequest $approvalRequest,
        ApprovalService $approvalService,
    ): RedirectResponse {
        Gate::authorize('decide', $approvalRequest);

        $note = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ])['note'] ?? null;

        $approvalService->approve($approvalRequest, $request->user(), $note);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $approvalRequest->type === ApprovalType::LoanWriteOff
                ? __('Write-off approved. The loan is now written off.')
                : __('Reversal approved. The payment has been reversed.'),
        ]);

        return back();
    }

    /**
     * Reject the request without changing the loan.
     */
    public function reject(
        Request $request,
        ApprovalRequest $approvalRequest,
        ApprovalService $approvalService,
    ): RedirectResponse {
        Gate::authorize('decide', $approvalRequest);

        $note = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ])['note'] ?? null;

        $approvalService->reject($approvalRequest, $request->user(), $note);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request rejected.')]);

        return back();
    }

    /**
     * Narrow the queue to the selected type and search term.
     */
    private function baseQuery(?ApprovalType $type, string $search): Builder
    {
        return ApprovalRequest::query()
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('loan.borrower', fn (Builder $borrower) => $borrower
                        ->whereAny(['name', 'phone'], 'like', "%{$search}%"));

                    $digits = ltrim($search, '#');

                    if (ctype_digit($digits)) {
                        $query->orWhere('loan_id', (int) $digits)
                            ->orWhereKey((int) $digits);
                    }
                });
            });
    }

    /**
     * Narrow the queue to the selected status tab.
     */
    private function applyTab(Builder $query, string $tab): Builder
    {
        return $query
            ->when($tab === 'pending', fn (Builder $query) => $query->where('status', ApprovalStatus::Pending))
            ->when($tab === 'approved', fn (Builder $query) => $query->where('status', ApprovalStatus::Approved))
            ->when($tab === 'rejected', fn (Builder $query) => $query->where('status', ApprovalStatus::Rejected));
    }

    /**
     * Transform a request into the payload shared with the queue.
     *
     * @return array<string, mixed>
     */
    private function toPayload(ApprovalRequest $approval, User $actor): array
    {
        return [
            'id' => $approval->id,
            'type' => $approval->type->value,
            'type_label' => $approval->type->label(),
            'status' => $approval->status->value,
            'status_label' => $approval->status->label(),
            'loan_id' => $approval->loan_id,
            'borrower_id' => $approval->loan->borrower_id,
            'borrower_name' => $approval->loan->borrower?->name ?? __('Unknown borrower'),
            'amount' => number_format((float) $approval->amount, 2, '.', ''),
            'reason' => $approval->reason,
            'requested_by' => $approval->requestedBy?->name,
            'requested_at' => $approval->created_at?->toIso8601String(),
            'reviewed_by' => $approval->reviewedBy?->name,
            'reviewed_at' => $approval->reviewed_at?->toIso8601String(),
            'review_note' => $approval->review_note,
            'is_requester' => (int) $approval->requested_by === (int) $actor->id,
            'can_decide' => $actor->can('decide', $approval),
        ];
    }
}
