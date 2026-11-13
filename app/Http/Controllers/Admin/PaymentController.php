<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Services\CsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    /**
     * The status tabs, in display order.
     *
     * @var array<int, string>
     */
    private const TABS = ['all', 'completed', 'reversed'];

    public function __construct(private readonly CsvExporter $csvExporter) {}

    /**
     * Display the payments recorded across the book.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $this->filters($request);
        $base = $this->baseQuery($filters);

        $counts = [
            'all' => (clone $base)->count(),
            'completed' => (clone $base)->whereNull('reversed_at')->count(),
            'reversed' => (clone $base)->whereNotNull('reversed_at')->count(),
        ];

        $summaryQuery = $this->baseQuery($filters);

        $paymentsQuery = $this->baseQuery($filters)
            ->with(['loan.borrower:id,name,phone', 'recordedBy:id,name']);
        $this->applyStatus($paymentsQuery, $filters['status']);
        $this->applySort($paymentsQuery, $filters);

        $payments = $paymentsQuery
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Payment $payment): array => $this->toPayload($payment));

        return Inertia::render('admin/payments/index', [
            'payments' => $payments,
            'counts' => $counts,
            'summary' => [
                'count' => (clone $summaryQuery)->count(),
                'received' => number_format(
                    (float) (clone $summaryQuery)->whereNull('reversed_at')->sum('amount'),
                    2,
                    '.',
                    '',
                ),
                'reversed' => (clone $summaryQuery)->whereNotNull('reversed_at')->count(),
            ],
            'methods' => $this->methodOptions(),
            'currentStatus' => $filters['status'],
            'currentMethod' => $filters['method']?->value,
            'recorders' => $this->recorderOptions(),
            'currentRecorder' => $filters['recorded_by'],
            'dateFrom' => $filters['from'],
            'dateTo' => $filters['to'],
            'currentBorrower' => $this->borrowerFilter($filters['borrower']),
            'currentLoan' => $this->loanFilter($filters['loan']),
            'search' => $filters['search'],
            'currentSort' => $filters['sort'],
            'currentDirection' => $filters['direction'],
        ]);
    }

    /**
     * Stream the filtered payments as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $this->filters($request);

        $query = $this->baseQuery($filters)
            ->with(['loan.borrower:id,name,phone', 'recordedBy:id,name']);
        $this->applyStatus($query, $filters['status']);
        $this->applySort($query, $filters);

        return $this->csvExporter->stream(
            'payments-'.now()->format('Y-m-d').'.csv',
            $this->csvRows($query->lazy(500)),
        );
    }

    /**
     * Parse the payment register filters from the request.
     *
     * @return array{status: string, method: PaymentMethod|null, recorded_by: int|null, borrower: int|null, loan: int|null, from: string|null, to: string|null, search: string, sort: string, direction: 'asc'|'desc'}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $borrowerId = $request->integer('borrower');
        $loanId = $request->integer('loan');
        $recordedById = $request->integer('recorded_by');

        return [
            'status' => in_array($status, self::TABS, true) ? $status : 'all',
            'method' => PaymentMethod::tryFrom($request->string('method')->toString()),
            'recorded_by' => $recordedById > 0 && User::query()->whereKey($recordedById)->exists()
                ? $recordedById
                : null,
            'borrower' => $borrowerId > 0 && Borrower::query()->whereKey($borrowerId)->exists()
                ? $borrowerId
                : null,
            'loan' => $loanId > 0 && Loan::query()->whereKey($loanId)->exists()
                ? $loanId
                : null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'search' => trim($request->string('search')->toString()),
            'sort' => in_array($sort, ['payment_date', 'amount'], true) ? $sort : 'payment_date',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
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
     * The payments that survive the borrower, loan, recorder, method, date and search filters.
     *
     * @param  array{status: string, method: PaymentMethod|null, recorded_by: int|null, borrower: int|null, loan: int|null, from: string|null, to: string|null, search: string, sort: string, direction: 'asc'|'desc'}  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $search = $filters['search'];

        return Payment::query()
            ->when($filters['recorded_by'] !== null, fn (Builder $query) => $query->where('recorded_by', $filters['recorded_by']))
            ->when($filters['borrower'] !== null, fn (Builder $query) => $query->whereHas(
                'loan',
                fn (Builder $loan) => $loan->where('borrower_id', $filters['borrower']),
            ))
            ->when($filters['loan'] !== null, fn (Builder $query) => $query->where('loan_id', $filters['loan']))
            ->when($filters['method'], fn (Builder $query, PaymentMethod $method) => $query->where('method', $method))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->whereDate('payment_date', '>=', $filters['from']))
            ->when($filters['to'] !== null, fn (Builder $query) => $query->whereDate('payment_date', '<=', $filters['to']))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('loan.borrower', fn (Builder $borrower) => $borrower
                            ->whereAny(
                                ['name', 'phone', 'national_id'],
                                'like',
                                "%{$search}%",
                            ));

                    $digits = ltrim($search, '#');

                    if (ctype_digit($digits)) {
                        $query->orWhereKey((int) $digits)
                            ->orWhere('loan_id', (int) $digits);
                    }
                });
            });
    }

    /**
     * Narrow the register to the selected status tab.
     */
    private function applyStatus(Builder $query, string $status): void
    {
        $query
            ->when($status === 'completed', fn (Builder $query) => $query->whereNull('reversed_at'))
            ->when($status === 'reversed', fn (Builder $query) => $query->whereNotNull('reversed_at'));
    }

    /**
     * Apply the requested order, breaking ties by newest payment.
     *
     * @param  array{status: string, method: PaymentMethod|null, recorded_by: int|null, borrower: int|null, loan: int|null, from: string|null, to: string|null, search: string, sort: string, direction: 'asc'|'desc'}  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $query
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderByDesc('id');
    }

    /**
     * Map the payment methods to select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function methodOptions(): array
    {
        return array_map(
            fn (PaymentMethod $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
            ],
            PaymentMethod::cases(),
        );
    }

    /**
     * Map the staff who have recorded at least one payment to select options.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function recorderOptions(): array
    {
        return User::query()
            ->whereIn('id', Payment::query()->whereNotNull('recorded_by')->select('recorded_by'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'value' => $user->id,
                'label' => $user->name,
            ])
            ->all();
    }

    /**
     * Transform one payment into the payload shared with the client.
     *
     * @return array<string, mixed>
     */
    private function toPayload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'loan_id' => $payment->loan_id,
            'borrower_id' => $payment->loan->borrower_id,
            'borrower_name' => $payment->loan->borrower->name,
            'borrower_phone' => $payment->loan->borrower->phone,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'method' => $payment->method->value,
            'method_label' => $payment->method->label(),
            'reference' => $payment->reference,
            'payment_date' => $payment->payment_date->toDateString(),
            'recorded_by' => $payment->recordedBy?->name,
            'reversed' => $payment->reversed_at !== null,
            'reversed_at' => $payment->reversed_at?->toDateString(),
            'reversal_reason' => $payment->reversal_reason,
        ];
    }

    /**
     * Build the CSV rows lazily so a long register streams in chunks.
     *
     * @param  iterable<int, Payment>  $payments
     * @return \Generator<int, array<int, string|int|null>>
     */
    private function csvRows(iterable $payments): \Generator
    {
        yield [
            'Payment #',
            'Date',
            'Loan #',
            'Borrower',
            'Phone',
            'Method',
            'Reference',
            'Amount',
            'Status',
            'Recorded by',
            'Reversed at',
            'Reversal reason',
        ];

        foreach ($payments as $payment) {
            $row = $this->toPayload($payment);

            yield [
                $row['id'],
                $row['payment_date'],
                $row['loan_id'],
                $row['borrower_name'],
                $row['borrower_phone'],
                $row['method_label'],
                $row['reference'],
                $row['amount'],
                $row['reversed'] ? 'Reversed' : 'Completed',
                $row['recorded_by'],
                $row['reversed_at'],
                $row['reversal_reason'],
            ];
        }
    }
}
