<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\CsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalController extends Controller
{
    /**
     * The status tabs, in display order.
     *
     * @var array<int, string>
     */
    private const TABS = ['pending', 'approved', 'rejected', 'all'];

    public function __construct(private readonly CsvExporter $csvExporter) {}

    /**
     * Display the maker-checker queue.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ApprovalRequest::class);

        $actor = $request->user();
        $filters = $this->filters($request);

        $base = $this->baseQuery($filters['type'], $filters['search']);

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
        ]), $filters['tab'])
            ->latest()
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (ApprovalRequest $approval): array => $this->toPayload($approval, $actor));

        return Inertia::render('admin/approvals/index', [
            'approvals' => $approvals,
            'counts' => $counts,
            'currentTab' => $filters['tab'],
            'currentType' => $filters['type']?->value,
            'types' => array_map(fn (ApprovalType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], ApprovalType::cases()),
            'search' => $filters['search'],
        ]);
    }

    /**
     * Export the filtered queue as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', ApprovalRequest::class);

        $filters = $this->filters($request);

        return $this->csvExporter->stream(
            'approvals-'.now()->format('Y-m-d').'.csv',
            $this->csvRows(
                $this->applyTab(
                    $this->baseQuery($filters['type'], $filters['search']),
                    $filters['tab'],
                ),
            ),
        );
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
     * Parse the queue filters shared by the page and the CSV export.
     *
     * @return array{tab: string, type: ApprovalType|null, search: string}
     */
    private function filters(Request $request): array
    {
        $tab = $request->string('tab')->toString();

        return [
            'tab' => in_array($tab, self::TABS, true) ? $tab : 'pending',
            'type' => ApprovalType::tryFrom($request->string('type')->toString()),
            'search' => trim($request->string('search')->toString()),
        ];
    }

    /**
     * Build the CSV rows for the filtered queue.
     *
     * @return \Generator<int, array<int, string|int|null>>
     */
    private function csvRows(Builder $query): \Generator
    {
        yield [
            'Request #',
            'Type',
            'Status',
            'Loan #',
            'Borrower',
            'Amount',
            'Reason',
            'Requested by',
            'Requested at',
            'Reviewed by',
            'Reviewed at',
            'Review note',
        ];

        $approvals = $query
            ->with([
                'loan.borrower:id,name',
                'requestedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->latest()
            ->latest('id')
            ->lazy(500);

        foreach ($approvals as $approval) {
            yield [
                $approval->id,
                $approval->type->label(),
                $approval->status->label(),
                $approval->loan_id,
                $approval->loan?->borrower?->name ?? __('Unknown borrower'),
                number_format((float) $approval->amount, 2, '.', ''),
                $approval->reason,
                $approval->requestedBy?->name,
                $approval->created_at?->toDateTimeString(),
                $approval->reviewedBy?->name,
                $approval->reviewed_at?->toDateTimeString(),
                $approval->review_note,
            ];
        }
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
