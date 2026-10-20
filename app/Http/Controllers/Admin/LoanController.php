<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisbursementMethod;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DisburseLoanRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\RestructureLoanRequest;
use App\Http\Requests\Admin\SendLoanRemindersRequest;
use App\Http\Requests\Admin\StoreLoanRequest;
use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\LoanInstallment;
use App\Models\LoanPaymentAllocation;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Services\LoanService;
use App\Services\NotificationService;
use App\Services\RepaymentService;
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
        $overdueOnly = $request->boolean('overdue');
        $search = trim($request->string('search')->toString());

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['principal', 'applied_at', 'arrears'], true) ? $sort : null;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $applySearch = function (Builder $query) use ($search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereHas('borrower', fn (Builder $borrower) => $borrower
                    ->where('name', 'like', "%{$search}%"));

                if (ctype_digit($search)) {
                    $query->orWhereKey((int) $search);
                }
            });
        };

        $statusCounts = Loan::query()
            ->when($overdueOnly, fn (Builder $query) => $query->overdue())
            ->when($search !== '', fn (Builder $query) => $applySearch($query))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (Loan $loan): array => [
                $loan->status->value => (int) $loan->aggregate,
            ]);

        $loans = Loan::query()
            ->with(['borrower', 'loanProduct', 'installments.allocations'])
            ->when($status, fn (Builder $query, LoanStatus $loanStatus) => $query->ofStatus($loanStatus))
            ->when($overdueOnly, fn (Builder $query) => $query->overdue())
            ->when($search !== '', fn (Builder $query) => $applySearch($query))
            ->when($sort === null, fn (Builder $query) => $query->latest())
            ->when($sort === 'arrears', fn (Builder $query) => $query->orderByArrears($direction))
            ->when(
                in_array($sort, ['principal', 'applied_at'], true),
                fn (Builder $query) => $query->orderBy($sort, $direction),
            )
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Loan $loan): array => [
                ...$this->toPayload($loan),
                'arrears' => number_format($this->loanArrears($loan), 2, '.', ''),
                'can' => [
                    'view' => $actor->can('view', $loan),
                    'statement' => $actor->can('view', $loan) && filled($loan->borrower->email),
                    'delete' => $actor->can('delete', $loan),
                ],
            ]);

        return Inertia::render('admin/loans/index', [
            'loans' => $loans,
            'statuses' => $this->statusOptions(),
            'currentStatus' => $status?->value,
            'overdueOnly' => $overdueOnly,
            'search' => $search,
            'currentSort' => $sort,
            'currentDirection' => $direction,
            'counts' => [
                'all' => (int) $statusCounts->sum(),
                ...collect(LoanStatus::cases())
                    ->mapWithKeys(fn (LoanStatus $case): array => [
                        $case->value => $statusCounts->get($case->value, 0),
                    ])
                    ->all(),
            ],
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

        $loan->load([
            'borrower',
            'loanProduct',
            'installments.allocations',
            'disbursements.recordedBy',
            'payments.recordedBy',
            'payments.allocations.installment',
            'events.user',
            'notificationLogs',
        ]);

        $actor = $request->user();
        $currentTab = $request->string('tab')->toString();
        $currentTab = in_array($currentTab, ['overview', 'schedule', 'payments', 'disbursements', 'notifications', 'activity'], true)
            ? $currentTab
            : 'overview';

        return Inertia::render('admin/loans/show', [
            'loan' => $this->toPayload($loan),
            'installments' => $this->installmentPayload($loan),
            'disbursements' => $this->disbursementPayload($loan),
            'payments' => $this->paymentPayload($loan),
            'totals' => $this->paymentTotals($loan),
            'events' => $this->eventPayload($loan),
            'notifications' => $this->notificationPayload($loan),
            'disbursementMethods' => $this->disbursementMethodOptions(),
            'paymentMethods' => $this->paymentMethodOptions(),
            'currentTab' => $currentTab,
            'can' => [
                'approve' => $actor->can('approve', $loan),
                'reject' => $actor->can('reject', $loan),
                'disburse' => $actor->can('disburse', $loan),
                'pay' => $actor->can('recordPayment', $loan),
                'default' => $actor->can('markDefaulted', $loan),
                'write_off' => $actor->can('writeOff', $loan),
                'restructure' => $actor->can('restructure', $loan),
                'statement' => $actor->can('view', $loan) && filled($loan->borrower->email),
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
     * Record a repayment against the given loan.
     */
    public function recordPayment(RecordPaymentRequest $request, Loan $loan, RepaymentService $repaymentService): RedirectResponse
    {
        $data = $request->validated();

        $repaymentService->recordPayment(
            $loan,
            $request->user(),
            (float) $data['amount'],
            PaymentMethod::from($data['method']),
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            isset($data['payment_date']) ? Date::parse($data['payment_date']) : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return to_route('admin.loans.show', $loan);
    }

    /**
     * Flag the given loan as defaulted.
     */
    public function markDefaulted(Request $request, Loan $loan, LoanService $loanService): RedirectResponse
    {
        Gate::authorize('markDefaulted', $loan);

        $loanService->markDefaulted($loan, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan marked as defaulted.')]);

        return to_route('admin.loans.show', $loan);
    }

    /**
     * Write off the given loan.
     */
    public function writeOff(Request $request, Loan $loan, LoanService $loanService): RedirectResponse
    {
        Gate::authorize('writeOff', $loan);

        $loanService->writeOff($loan, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan written off.')]);

        return to_route('admin.loans.show', $loan);
    }

    /**
     * Restructure the given loan onto a fresh schedule.
     */
    public function restructure(RestructureLoanRequest $request, Loan $loan, LoanService $loanService): RedirectResponse
    {
        $data = $request->validated();

        $loanService->restructure(
            $loan,
            $request->user(),
            (int) $data['term_months'],
            isset($data['starts_on']) ? Date::parse($data['starts_on']) : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loan restructured.')]);

        return to_route('admin.loans.show', $loan);
    }

    /**
     * Email overdue reminders for the selected loans.
     */
    public function sendReminders(
        SendLoanRemindersRequest $request,
        NotificationService $notificationService,
    ): RedirectResponse {
        $loans = Loan::query()
            ->whereIn('id', $request->validated('loans'))
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($loans as $loan) {
            if ($notificationService->sendOverdueNotice($loan)) {
                $sent++;
            } else {
                $skipped++;
            }
        }

        $message = trans_choice(
            '{0} No reminders were sent. Borrowers may have opted out or already received a notice today.|{1} :count overdue reminder queued.|[2,*] :count overdue reminders queued.',
            $sent,
            ['count' => $sent],
        );

        if ($sent > 0 && $skipped > 0) {
            $message .= ' '.__(':count skipped.', ['count' => $skipped]);
        }

        Inertia::flash('toast', [
            'type' => $sent > 0 ? 'success' : 'warning',
            'message' => $message,
        ]);

        return back();
    }

    /**
     * Email the borrower a statement for the given loan.
     */
    public function sendStatement(Loan $loan, NotificationService $notificationService): RedirectResponse
    {
        Gate::authorize('view', $loan);

        if (! $notificationService->sendStatement($loan)) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('The statement was not sent. Add an email address and enable statements for this borrower.'),
            ]);

            return to_route('admin.loans.show', $loan);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Statement emailed to :email.', ['email' => $loan->borrower->email]),
        ]);

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
            'penalty_rate' => $loan->penalty_rate,
            'grace_period_days' => $loan->grace_period_days,
            'days_past_due' => $loan->days_past_due,
            'status' => $loan->status->value,
            'status_label' => $loan->status->label(),
            'applied_at' => $loan->applied_at?->toIso8601String(),
            'approved_at' => $loan->approved_at?->toIso8601String(),
            'rejected_at' => $loan->rejected_at?->toIso8601String(),
            'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
            'closed_at' => $loan->closed_at?->toIso8601String(),
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
            ->map(function (LoanInstallment $installment): array {
                $paid = round(
                    (float) $installment->allocations->sum(fn (LoanPaymentAllocation $allocation): float => (float) $allocation->principal_allocated
                        + (float) $allocation->interest_allocated
                        + (float) $allocation->fee_allocated
                        + (float) $allocation->penalty_allocated),
                    2,
                );

                return [
                    'sequence' => $installment->sequence,
                    'due_date' => $installment->due_date->toIso8601String(),
                    'principal_due' => $installment->principal_due,
                    'interest_due' => $installment->interest_due,
                    'fee_due' => $installment->fee_due,
                    'balance' => $installment->balance,
                    'penalty_accrued' => $installment->penalty_accrued,
                    'paid' => number_format($paid, 2, '.', ''),
                    'outstanding' => number_format($installment->outstandingTotal(), 2, '.', ''),
                    'principal_outstanding' => number_format($installment->outstandingPrincipal(), 2, '.', ''),
                    'interest_outstanding' => number_format($installment->outstandingInterest(), 2, '.', ''),
                    'fee_outstanding' => number_format($installment->outstandingFee(), 2, '.', ''),
                    'penalty_outstanding' => number_format($installment->outstandingPenalty(), 2, '.', ''),
                    'overdue' => $installment->isOverdue(),
                    'days_past_due' => $installment->daysPastDue(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Sum the overdue outstanding amount across the loan's installments.
     */
    private function loanArrears(Loan $loan): float
    {
        return round($loan->installments->sum(
            fn (LoanInstallment $installment): float => $installment->isOverdue() ? $installment->outstandingTotal() : 0.0,
        ), 2);
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
     * Transform the recorded payments into the payload shared with the client.
     *
     * @return array<int, array<string, mixed>>
     */
    private function paymentPayload(Loan $loan): array
    {
        return $loan->payments
            ->sortByDesc('payment_date')
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'payment_date' => $payment->payment_date->toIso8601String(),
                'recorded_by' => $payment->recordedBy?->name,
                'allocations' => $payment->allocations
                    ->map(fn (LoanPaymentAllocation $allocation): array => [
                        'installment_sequence' => $allocation->sequence,
                        'principal_allocated' => $allocation->principal_allocated,
                        'interest_allocated' => $allocation->interest_allocated,
                        'fee_allocated' => $allocation->fee_allocated,
                        'penalty_allocated' => $allocation->penalty_allocated,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Summarise the amounts payable, paid, outstanding, in arrears, penalties
     * and the aging of the overdue balance.
     *
     * @return array<string, mixed>
     */
    private function paymentTotals(Loan $loan): array
    {
        $payable = 0.0;
        $paid = round((float) $loan->payments->sum('amount'), 2);
        $outstanding = 0.0;
        $arrears = 0.0;
        $penalty = 0.0;
        $outstandingPrincipal = 0.0;
        $outstandingInterest = 0.0;
        $outstandingFee = 0.0;

        $aging = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_over_90' => 0.0,
        ];

        foreach ($loan->installments as $installment) {
            $outstandingDue = $installment->outstandingDue();
            $outstandingPenalty = $installment->outstandingPenalty();
            $installmentOutstanding = $installment->outstandingTotal();

            $payable += $installment->dueAmount();
            $outstanding += $installmentOutstanding;
            $penalty += $outstandingPenalty;
            $outstandingPrincipal += $installment->outstandingPrincipal();
            $outstandingInterest += $installment->outstandingInterest();
            $outstandingFee += $installment->outstandingFee();

            if ($installment->isOverdue()) {
                $arrears += $installmentOutstanding;

                $days = $installment->daysPastDue();
                $bucket = match (true) {
                    $days <= 30 => 'days_1_30',
                    $days <= 60 => 'days_31_60',
                    $days <= 90 => 'days_61_90',
                    default => 'days_over_90',
                };

                $aging[$bucket] += $installmentOutstanding;
            } else {
                $aging['current'] += $outstandingDue + $outstandingPenalty;
            }
        }

        return [
            'payable' => number_format(round($payable, 2), 2, '.', ''),
            'paid' => number_format(round($paid, 2), 2, '.', ''),
            'outstanding' => number_format(round($outstanding, 2), 2, '.', ''),
            'arrears' => number_format(round($arrears, 2), 2, '.', ''),
            'penalty' => number_format(round($penalty, 2), 2, '.', ''),
            'allocation' => [
                'penalty' => number_format(round($penalty, 2), 2, '.', ''),
                'fee' => number_format(round($outstandingFee, 2), 2, '.', ''),
                'interest' => number_format(round($outstandingInterest, 2), 2, '.', ''),
                'principal' => number_format(round($outstandingPrincipal, 2), 2, '.', ''),
            ],
            'aging' => array_map(
                fn (float $amount): string => number_format(round($amount, 2), 2, '.', ''),
                $aging,
            ),
        ];
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
     * Transform the loan's email notifications into the payload shared with
     * the client.
     *
     * @return array<int, array<string, mixed>>
     */
    private function notificationPayload(Loan $loan): array
    {
        return $loan->notificationLogs
            ->sortByDesc('sent_at')
            ->map(fn (NotificationLog $log): array => [
                'id' => $log->id,
                'type' => $log->type,
                'type_label' => NotificationLog::label($log->type),
                'channel' => $log->channel,
                'sent_at' => $log->sent_at->toIso8601String(),
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
     * Map the payment methods to select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function paymentMethodOptions(): array
    {
        return array_map(
            fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()],
            PaymentMethod::cases(),
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
