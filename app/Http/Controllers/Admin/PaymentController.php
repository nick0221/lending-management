<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Payment;
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
            'dateFrom' => $filters['from'],
            'dateTo' => $filters['to'],
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
     * @return array{status: string, method: PaymentMethod|null, from: string|null, to: string|null, search: string, sort: string, direction: 'asc'|'desc'}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();

        return [
            'status' => in_array($status, self::TABS, true) ? $status : 'all',
            'method' => PaymentMethod::tryFrom($request->string('method')->toString()),
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'search' => trim($request->string('search')->toString()),
            'sort' => in_array($sort, ['payment_date', 'amount'], true) ? $sort : 'payment_date',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * The payments that survive the method, date and search filters.
     *
     * @param  array{status: string, method: PaymentMethod|null, from: string|null, to: string|null, search: string, sort: string, direction: 'asc'|'desc'}  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $search = $filters['search'];

        return Payment::query()
            ->when($filters['method'], fn (Builder $query, PaymentMethod $method) => $query->where('method', $method))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->whereDate('payment_date', '>=', $filters['from']))
            ->when($filters['to'] !== null, fn (Builder $query) => $query->whereDate('payment_date', '<=', $filters['to']))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('loan.borrower', fn (Builder $borrower) => $borrower
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));

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
     * @param  array{status: string, method: PaymentMethod|null, from: string|null, to: string|null, search: string, sort: string, direction: 'asc'|'desc'}  $filters
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
