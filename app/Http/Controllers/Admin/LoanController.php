<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisbursementMethod;
use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DisburseLoanRequest;
use App\Http\Requests\Admin\StoreLoanRequest;
use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Services\LoanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    /**
     * Display a listing of the loans.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Loan::class);

        $actor = $request->user();
        $status = LoanStatus::tryFrom($request->string('status')->toString());

        $loans = Loan::query()
            ->with(['borrower', 'loanProduct'])
            ->when($status, fn (Builder $query, LoanStatus $loanStatus) => $query->ofStatus($loanStatus))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Loan $loan): array => [
                ...$this->toPayload($loan),
                'can' => [
                    'view' => $actor->can('view', $loan),
                    'delete' => $actor->can('delete', $loan),
                ],
            ]);

        return Inertia::render('admin/loans/index', [
            'loans' => $loans,
            'statuses' => $this->statusOptions(),
            'currentStatus' => $status?->value,
        ]);
    }

    /**
     * Show the form for originating a new loan.
     */
    public function create(): Response
    {
        Gate::authorize('create', Loan::class);

        return Inertia::render('admin/loans/create', [
            'borrowers' => $this->borrowerOptions(),
            'loanProducts' => $this->loanProductOptions(),
        ]);
    }

    /**
     * Store a newly originated loan.
     */
    public function store(StoreLoanRequest $request, LoanService $loanService): RedirectResponse
    {
        $data = $request->validated();

        $loan = $loanService->originate(
            Borrower::findOrFail($data['borrower_id']),
            LoanProduct::findOrFail($data['loan_product_id']),
            (float) $data['principal'],
            (int) $data['term_months'],
            $data['notes'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan created.')]);

        return to_route('admin.loans.show', $loan);
    }

    /**
     * Display the given loan.
     */
    public function show(Request $request, Loan $loan): Response
    {
        Gate::authorize('view', $loan);

        $loan->load(['borrower', 'loanProduct', 'installments', 'disbursements.recordedBy', 'events.user']);

        $actor = $request->user();

        return Inertia::render('admin/loans/show', [
            'loan' => $this->toPayload($loan),
            'installments' => $this->installmentPayload($loan),
            'disbursements' => $this->disbursementPayload($loan),
            'events' => $this->eventPayload($loan),
            'disbursementMethods' => $this->disbursementMethodOptions(),
            'can' => [
                'approve' => $actor->can('approve', $loan),
                'reject' => $actor->can('reject', $loan),
                'disburse' => $actor->can('disburse', $loan),
                'delete' => $actor->can('delete', $loan),
            ],
        ]);
    }

    /**
     * Approve the given loan.
     */
    public function approve(Request $request, Loan $loan, LoanService $loanService): RedirectResponse
    {
        Gate::authorize('approve', $loan);

        $loanService->approve($loan, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan approved.')]);

        return to_route('admin.loans.show', $loan);
    }

    /**
     * Reject the given loan.
     */
    public function reject(Request $request, Loan $loan, LoanService $loanService): RedirectResponse
    {
        Gate::authorize('reject', $loan);

        $loanService->reject($loan, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan rejected.')]);

        return to_route('admin.loans.show', $loan);
    }

    /**
     * Disburse the given loan.
     */
    public function disburse(DisburseLoanRequest $request, Loan $loan, LoanService $loanService): RedirectResponse
    {
        $data = $request->validated();

        $loanService->disburse(
            $loan,
            $request->user(),
            (float) $data['amount'],
            DisbursementMethod::from($data['method']),
            $data['reference'] ?? null,
            isset($data['disbursed_at']) ? Date::parse($data['disbursed_at']) : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan disbursed.')]);

        return to_route('admin.loans.show', $loan);
    }

    /**
     * Remove the given loan.
     */
    public function destroy(Loan $loan): RedirectResponse
    {
        Gate::authorize('delete', $loan);

        $loan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan deleted.')]);

        return to_route('admin.loans.index');
    }

    /**
     * Transform the loan into the payload shared with the client.
     *
     * @return array<string, mixed>
     */
    private function toPayload(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'borrower_id' => $loan->borrower_id,
            'borrower_name' => $loan->borrower->name,
            'loan_product_id' => $loan->loan_product_id,
            'loan_product_name' => $loan->loanProduct->name,
            'principal' => $loan->principal,
            'interest_rate' => $loan->interest_rate,
            'interest_method' => $loan->interest_method->value,
            'interest_method_label' => $loan->interest_method->label(),
            'repayment_frequency' => $loan->repayment_frequency->value,
            'repayment_frequency_label' => $loan->repayment_frequency->label(),
            'term_months' => $loan->term_months,
            'processing_fee_rate' => $loan->processing_fee_rate,
            'status' => $loan->status->value,
            'status_label' => $loan->status->label(),
            'applied_at' => $loan->applied_at?->toIso8601String(),
            'approved_at' => $loan->approved_at?->toIso8601String(),
            'rejected_at' => $loan->rejected_at?->toIso8601String(),
            'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
            'notes' => $loan->notes,
            'created_at' => $loan->created_at?->toIso8601String(),
        ];
    }

    /**
     * Transform the repayment schedule into the payload shared with the client.
     *
     * @return array<int, array<string, mixed>>
     */
    private function installmentPayload(Loan $loan): array
    {
        return $loan->installments
            ->sortBy('sequence')
            ->map(fn (LoanInstallment $installment): array => [
                'sequence' => $installment->sequence,
                'due_date' => $installment->due_date->toIso8601String(),
                'principal_due' => $installment->principal_due,
                'interest_due' => $installment->interest_due,
                'fee_due' => $installment->fee_due,
                'balance' => $installment->balance,
            ])
            ->values()
            ->all();
    }

    /**
     * Transform the disbursements into the payload shared with the client.
     *
     * @return array<int, array<string, mixed>>
     */
    private function disbursementPayload(Loan $loan): array
    {
        return $loan->disbursements
            ->sortByDesc('disbursed_at')
            ->map(fn (Disbursement $disbursement): array => [
                'id' => $disbursement->id,
                'amount' => $disbursement->amount,
                'method' => $disbursement->method->value,
                'method_label' => $disbursement->method->label(),
                'reference' => $disbursement->reference,
                'disbursed_at' => $disbursement->disbursed_at->toIso8601String(),
                'recorded_by' => $disbursement->recordedBy?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Transform the loan's audit trail into the payload shared with the client.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventPayload(Loan $loan): array
    {
        return $loan->events
            ->sortBy('created_at')
            ->map(fn (LoanEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action->value,
                'action_label' => $event->action->label(),
                'from_status' => $event->from_status?->value,
                'from_status_label' => $event->from_status?->label(),
                'to_status' => $event->to_status?->value,
                'to_status_label' => $event->to_status?->label(),
                'user' => $event->user?->name,
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Map the disbursement methods to select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function disbursementMethodOptions(): array
    {
        return array_map(
            fn (DisbursementMethod $method): array => ['value' => $method->value, 'label' => $method->label()],
            DisbursementMethod::cases(),
        );
    }

    /**
     * Map the loan statuses to select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (LoanStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            LoanStatus::cases(),
        );
    }

    /**
     * Map the borrowers to select options.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function borrowerOptions(): array
    {
        return Borrower::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Borrower $borrower): array => [
                'value' => $borrower->id,
                'label' => $borrower->name,
            ])
            ->all();
    }

    /**
     * Map the active loan products to select options, including their terms so
     * the client can guide the principal and term inputs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loanProductOptions(): array
    {
        return LoanProduct::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (LoanProduct $loanProduct): array => [
                'value' => $loanProduct->id,
                'label' => $loanProduct->name,
                'interest_rate' => $loanProduct->interest_rate,
                'interest_method_label' => $loanProduct->interest_method->label(),
                'repayment_frequency_label' => $loanProduct->repayment_frequency->label(),
                'min_amount' => $loanProduct->min_amount,
                'max_amount' => $loanProduct->max_amount,
                'min_term_months' => $loanProduct->min_term_months,
                'max_term_months' => $loanProduct->max_term_months,
            ])
            ->all();
    }
}
