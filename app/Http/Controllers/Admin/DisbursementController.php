<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisbursementMethod;
use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Services\CsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisbursementController extends Controller
{
    public function __construct(private readonly CsvExporter $csvExporter) {}

    /**
     * Display the payouts recorded across the book.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Disbursement::class);

        $filters = $this->filters($request);
        $summaryQuery = $this->baseQuery($filters);

        $disbursementsQuery = $this->baseQuery($filters)
            ->with(['loan.borrower:id,name,phone', 'recordedBy:id,name']);
        $this->applySort($disbursementsQuery, $filters);

        $disbursements = $disbursementsQuery
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Disbursement $disbursement): array => $this->toPayload($disbursement));

        $count = (clone $summaryQuery)->count();
        $total = (float) (clone $summaryQuery)->sum('amount');

        return Inertia::render('admin/disbursements/index', [
            'disbursements' => $disbursements,
            'summary' => [
                'count' => $count,
                'total' => number_format($total, 2, '.', ''),
                'average' => number_format($count > 0 ? $total / $count : 0, 2, '.', ''),
            ],
            'methods' => $this->methodOptions(),
            'currentMethod' => $filters['method']?->value,
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
     * Stream the filtered payouts as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Disbursement::class);

        $filters = $this->filters($request);

        $query = $this->baseQuery($filters)
            ->with(['loan.borrower:id,name,phone', 'recordedBy:id,name']);
        $this->applySort($query, $filters);

        return $this->csvExporter->stream(
            'disbursements-'.now()->format('Y-m-d').'.csv',
            $this->csvRows($query->lazy(500)),
        );
    }

    /**
     * Parse the disbursement register filters from the request.
     *
     * @return array{method: DisbursementMethod|null, borrower: int|null, loan: int|null, from: string|null, to: string|null, search: string, sort: string, direction: 'asc'|'desc'}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $sort = $request->string('sort')->toString();
        $borrowerId = $request->integer('borrower');
        $loanId = $request->integer('loan');

        return [
            'method' => DisbursementMethod::tryFrom($request->string('method')->toString()),
            'borrower' => $borrowerId > 0 && Borrower::query()->whereKey($borrowerId)->exists()
                ? $borrowerId
                : null,
            'loan' => $loanId > 0 && Loan::query()->whereKey($loanId)->exists()
                ? $loanId
                : null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'search' => trim($request->string('search')->toString()),
            'sort' => in_array($sort, ['disbursed_at', 'amount'], true) ? $sort : 'disbursed_at',
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
     * The payouts that survive the borrower, loan, method, date and search filters.
     *
     * @param  array{method: DisbursementMethod|null, borrower: int|null, loan: int|null, from: string|null, to: string|null, search: string, sort: string, direction: 'asc'|'desc'}  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $search = $filters['search'];

        return Disbursement::query()
            ->when($filters['borrower'] !== null, fn (Builder $query) => $query->whereHas(
                'loan',
                fn (Builder $loan) => $loan->where('borrower_id', $filters['borrower']),
            ))
            ->when($filters['loan'] !== null, fn (Builder $query) => $query->where('loan_id', $filters['loan']))
            ->when($filters['method'], fn (Builder $query, DisbursementMethod $method) => $query->where('method', $method))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->whereDate('disbursed_at', '>=', $filters['from']))
            ->when($filters['to'] !== null, fn (Builder $query) => $query->whereDate('disbursed_at', '<=', $filters['to']))
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
     * Apply the requested order, breaking ties by newest payout.
     *
     * @param  array{method: DisbursementMethod|null, borrower: int|null, loan: int|null, from: string|null, to: string|null, search: string, sort: string, direction: 'asc'|'desc'}  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $query
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderByDesc('id');
    }

    /**
     * Map the disbursement methods to select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function methodOptions(): array
    {
        return array_map(
            fn (DisbursementMethod $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
            ],
            DisbursementMethod::cases(),
        );
    }

    /**
     * Transform one payout into the payload shared with the client.
     *
     * @return array<string, mixed>
     */
    private function toPayload(Disbursement $disbursement): array
    {
        return [
            'id' => $disbursement->id,
            'loan_id' => $disbursement->loan_id,
            'borrower_id' => $disbursement->loan->borrower_id,
            'borrower_name' => $disbursement->loan->borrower->name,
            'borrower_phone' => $disbursement->loan->borrower->phone,
            'amount' => number_format((float) $disbursement->amount, 2, '.', ''),
            'method' => $disbursement->method->value,
            'method_label' => $disbursement->method->label(),
            'reference' => $disbursement->reference,
            'disbursed_at' => $disbursement->disbursed_at->toDateString(),
            'recorded_by' => $disbursement->recordedBy?->name,
        ];
    }

    /**
     * Build the CSV rows lazily so a long register streams in chunks.
     *
     * @param  iterable<int, Disbursement>  $disbursements
     * @return \Generator<int, array<int, string|int|null>>
     */
    private function csvRows(iterable $disbursements): \Generator
    {
        yield [
            'Disbursement #',
            'Date',
            'Loan #',
            'Borrower',
            'Phone',
            'Method',
            'Reference',
            'Amount',
            'Recorded by',
        ];

        foreach ($disbursements as $disbursement) {
            $row = $this->toPayload($disbursement);

            yield [
                $row['id'],
                $row['disbursed_at'],
                $row['loan_id'],
                $row['borrower_name'],
                $row['borrower_phone'],
                $row['method_label'],
                $row['reference'],
                $row['amount'],
                $row['recorded_by'],
            ];
        }
    }
}
