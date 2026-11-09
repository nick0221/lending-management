<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\NotificationLog;
use App\Services\CsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationController extends Controller
{
    /**
     * Delivery channels shown in the register.
     *
     * @var array<int, string>
     */
    private const CHANNELS = [
        NotificationLog::CHANNEL_MAIL,
        NotificationLog::CHANNEL_SMS,
    ];

    /**
     * Delivery statuses used by the filter tabs.
     *
     * @var array<int, string>
     */
    private const STATUSES = [
        NotificationLog::STATUS_QUEUED,
        NotificationLog::STATUS_SENT,
        NotificationLog::STATUS_FAILED,
        NotificationLog::STATUS_SKIPPED,
    ];

    /**
     * Notification types available in the register.
     *
     * @var array<int, string>
     */
    private const TYPES = [
        NotificationLog::TYPE_DUE_SOON,
        NotificationLog::TYPE_OVERDUE,
        NotificationLog::TYPE_RECEIPT,
        NotificationLog::TYPE_STATEMENT,
    ];

    public function __construct(private readonly CsvExporter $csvExporter) {}

    /**
     * Show every email and SMS delivery recorded for the book.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', NotificationLog::class);

        $actor = $request->user();
        $filters = $this->validatedFilters($request);
        $status = $filters['status'] ?? null;

        $countsQuery = $this->filteredQuery($filters)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->status => (int) $row->aggregate,
            ]);

        $logsQuery = $this->filteredQuery($filters)
            ->with(['borrower:id,name,phone,email,notify_sms,notify_due_soon,notify_overdue,notify_receipts,notify_statements', 'loan:id'])
            ->orderByDesc('id');
        $this->applyStatus($logsQuery, $status);

        $logs = $logsQuery
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (NotificationLog $log): array => [
                'id' => $log->id,
                'type' => $log->type,
                'type_label' => NotificationLog::label($log->type),
                'channel' => $log->channel,
                'channel_label' => NotificationLog::channelLabel($log->channel),
                'status' => $log->status,
                'status_label' => NotificationLog::statusLabel($log->status),
                'error' => $log->error,
                'borrower_id' => $log->borrower_id,
                'borrower_name' => $log->borrower?->name,
                'borrower_phone' => $log->borrower?->phone,
                'loan_id' => $log->loan_id,
                'sent_at' => $log->sent_at?->toIso8601String(),
                'created_at' => $log->created_at?->toIso8601String(),
                'can_resend' => $log->loan !== null
                    && $log->borrower !== null
                    && $actor->can('view', $log->loan)
                    && $log->borrower->wantsChannel($log->type, $log->channel),
            ]);

        return Inertia::render('admin/notifications/index', [
            'logs' => $logs,
            'counts' => [
                'all' => (int) $countsQuery->sum(),
                ...collect(self::STATUSES)
                    ->mapWithKeys(fn (string $status): array => [
                        $status => (int) $countsQuery->get($status, 0),
                    ])
                    ->all(),
            ],
            'channels' => collect(self::CHANNELS)
                ->map(fn (string $channel): array => [
                    'value' => $channel,
                    'label' => NotificationLog::channelLabel($channel),
                ])
                ->all(),
            'statuses' => collect(self::STATUSES)
                ->map(fn (string $status): array => [
                    'value' => $status,
                    'label' => NotificationLog::statusLabel($status),
                ])
                ->all(),
            'types' => collect(self::TYPES)
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'label' => NotificationLog::label($type),
                ])
                ->all(),
            'currentStatus' => $status,
            'currentChannel' => $filters['channel'] ?? null,
            'currentType' => $filters['type'] ?? null,
            'currentBorrower' => $this->borrowerFilter($filters['borrower'] ?? null),
            'currentLoan' => $this->loanFilter($filters['loan'] ?? null),
            'search' => $filters['search'] ?? '',
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ]);
    }

    /**
     * Stream the filtered delivery log as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', NotificationLog::class);

        return $this->csvExporter->stream(
            'notifications-'.now()->format('Y-m-d').'.csv',
            $this->csvRows($this->validatedFilters($request)),
        );
    }

    /**
     * Validate the filters shared by the page and the CSV export.
     *
     * @return array<string, string|int|null>
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', Rule::in(self::CHANNELS)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'borrower' => ['nullable', 'integer', 'min:1'],
            'loan' => ['nullable', 'integer', 'min:1'],
        ]);

        $borrowerId = (int) ($validated['borrower'] ?? 0);
        $loanId = (int) ($validated['loan'] ?? 0);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'channel' => $validated['channel'] ?? null,
            'status' => $validated['status'] ?? null,
            'type' => $validated['type'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'borrower' => $borrowerId > 0 && Borrower::query()->whereKey($borrowerId)->exists()
                ? $borrowerId
                : null,
            'loan' => $loanId > 0 && Loan::query()->whereKey($loanId)->exists()
                ? $loanId
                : null,
        ];
    }

    /**
     * The borrower a filtered register is narrowed to, if any.
     *
     * @return array{id: int, name: string}|null
     */
    private function borrowerFilter(?int $borrowerId): ?array
    {
        if ($borrowerId === null) {
            return null;
        }

        $borrower = Borrower::query()
            ->whereKey($borrowerId)
            ->first(['id', 'name']);

        return $borrower === null
            ? null
            : ['id' => $borrower->id, 'name' => $borrower->name];
    }

    /**
     * The loan a filtered register is narrowed to, if any.
     *
     * @return array{id: int, label: string}|null
     */
    private function loanFilter(?int $loanId): ?array
    {
        if ($loanId === null) {
            return null;
        }

        $loan = Loan::query()
            ->with('borrower:id,name')
            ->whereKey($loanId)
            ->first(['id', 'borrower_id']);

        if ($loan === null) {
            return null;
        }

        return [
            'id' => $loan->id,
            'label' => 'Loan #'.$loan->id.' · '.($loan->borrower?->name ?? 'Unknown borrower'),
        ];
    }

    /**
     * Apply the borrower, loan, channel, type, search and date filters to the delivery log.
     *
     * @param  array<string, string|int|null>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $search = (string) ($filters['search'] ?? '');

        return NotificationLog::query()
            ->when(
                $filters['borrower'] ?? null,
                fn (Builder $query, int $borrowerId) => $query->where('borrower_id', $borrowerId),
            )
            ->when(
                $filters['loan'] ?? null,
                fn (Builder $query, int $loanId) => $query->where('loan_id', $loanId),
            )
            ->when(
                $filters['channel'] ?? null,
                fn (Builder $query, string $channel) => $query->where('channel', $channel),
            )
            ->when(
                $filters['type'] ?? null,
                fn (Builder $query, string $type) => $query->where('type', $type),
            )
            ->when(
                $filters['from'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date),
            )
            ->when(
                $filters['to'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->whereHas('borrower', fn (Builder $borrower) => $borrower
                        ->whereAny(['name', 'email', 'phone', 'national_id'], 'like', "%{$search}%"));

                    if (ctype_digit($search)) {
                        $inner->orWhere('loan_id', (int) $search)
                            ->orWhereKey((int) $search);
                    }
                });
            });
    }

    /**
     * Narrow the register to one delivery status.
     */
    private function applyStatus(Builder $query, ?string $status): void
    {
        $query->when($status, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * Build the CSV rows lazily so a long delivery log streams in chunks.
     *
     * @param  array<string, string|int|null>  $filters
     * @return \Generator<int, array<int, string|int|null>>
     */
    private function csvRows(array $filters): \Generator
    {
        yield [
            'Recorded at',
            'Borrower',
            'Phone',
            'Loan #',
            'Type',
            'Channel',
            'Status',
            'Sent at',
            'Error',
        ];

        $logs = $this->filteredQuery($filters)
            ->with(['borrower:id,name,phone'])
            ->orderByDesc('id');
        $this->applyStatus($logs, $filters['status'] ?? null);

        foreach ($logs->lazy(500) as $log) {
            yield [
                $log->created_at?->toDateTimeString(),
                $log->borrower?->name,
                $log->borrower?->phone,
                $log->loan_id,
                NotificationLog::label($log->type),
                NotificationLog::channelLabel($log->channel),
                NotificationLog::statusLabel($log->status),
                $log->sent_at?->toDateTimeString(),
                $log->error,
            ];
        }
    }
}
