<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisbursementMethod;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveLoansRequest;
use App\Http\Requests\Admin\DisburseLoanRequest;
use App\Http\Requests\Admin\LoanCalculatorRequest;
use App\Http\Requests\Admin\PreviewLoanRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\RestructureLoanRequest;
use App\Http\Requests\Admin\ReversePaymentRequest;
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
use App\Models\User;
use App\Notifications\LoanStatement;
use App\Services\CsvExporter;
use App\Services\LoanService;
use App\Services\NotificationService;
use App\Services\RepaymentService;
use App\Services\ScheduleService;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $productId = $request->integer('product');
        $productId = $productId > 0 && LoanProduct::whereKey($productId)->exists()
            ? $productId
            : null;

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
            ->when($productId, fn (Builder $query) => $query->where('loan_product_id', $productId))
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
            ->when($productId, fn (Builder $query) => $query->where('loan_product_id', $productId))
            ->when($search !== '', fn (Builder $query) => $applySearch($query))
            ->when($sort === null, fn (Builder $query) => $query->latest())
            ->when($sort === 'arrears', fn (Builder $query) => $query->orderByArrears($direction))
            ->when(
                in_array($sort, ['principal', 'applied_at'], true),
                fn (Builder $query) => $query->orderBy($sort, $direction),
            )
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Loan $loan): array => [
                ...$this->toPayload($loan),
                'outstanding' => number_format(round(
                    $loan->installments->sum(
                        fn (LoanInstallment $installment): float => $installment->outstandingTotal(),
                    ),
                    2,
                ), 2, '.', ''),
                'arrears' => number_format($this->loanArrears($loan), 2, '.', ''),
                'can' => [
                    'view' => $actor->can('view', $loan),
                    'statement' => $actor->can('view', $loan) && filled($loan->borrower->email),
                    'download_statement' => $actor->can('view', $loan),
                    'approve' => $actor->can('approve', $loan) && $loan->status === LoanStatus::Pending,
                    'delete' => $actor->can('delete', $loan),
                ],
            ]);

        return Inertia::render('admin/loans/index', [
            'loans' => $loans,
            'statuses' => $this->statusOptions(),
            'products' => LoanProduct::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (LoanProduct $product): array => [
                    'value' => $product->id,
                    'label' => $product->name,
                ])
                ->all(),
            'currentStatus' => $status?->value,
            'currentProduct' => $productId,
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
    public function create(Request $request): Response
    {
        Gate::authorize('create', Loan::class);

        $selectedBorrowerId = $request->integer('borrower');

        return Inertia::render('admin/loans/create', [
            'borrowers' => $this->borrowerOptions(),
            'loanProducts' => $this->loanProductOptions(),
            'selectedBorrowerId' => $selectedBorrowerId > 0
                && Borrower::whereKey($selectedBorrowerId)->exists()
                    ? $selectedBorrowerId
                    : null,
        ]);
    }

    /**
     * Quote a full schedule for a product and terms without creating a loan.
     */
    public function calculator(LoanCalculatorRequest $request, ScheduleService $scheduleService): Response
    {
        $filters = [
            'loan_product_id' => $request->filled('loan_product_id') ? (string) $request->integer('loan_product_id') : '',
            'principal' => $request->filled('principal') ? (string) $request->input('principal') : '',
            'term_months' => $request->filled('term_months') ? (string) $request->integer('term_months') : '',
        ];

        $preview = null;

        if ($request->filled(['loan_product_id', 'principal', 'term_months'])) {
            $product = LoanProduct::findOrFail($request->integer('loan_product_id'));

            $preview = $scheduleService->preview(
                principal: (float) $request->input('principal'),
                annualRatePercent: (float) $product->interest_rate,
                interestMethod: $product->interest_method,
                termMonths: (int) $request->input('term_months'),
                repaymentFrequency: $product->repayment_frequency,
                processingFeeRatePercent: (float) $product->processing_fee_rate,
                startsOn: today(),
                limit: (int) $request->input('term_months'),
            );
        }

        return Inertia::render('admin/loans/calculator', [
            'loanProducts' => $this->loanProductOptions(),
            'filters' => $filters,
            'preview' => $preview,
        ]);
    }

    /**
     * Preview the schedule for terms on the create form without storing a
     * loan. The response is plain JSON so the form can refresh it live.
     */
    public function preview(PreviewLoanRequest $request, ScheduleService $scheduleService): JsonResponse
    {
        $product = LoanProduct::findOrFail($request->integer('loan_product_id'));

        return response()->json($scheduleService->preview(
            principal: (float) $request->input('principal'),
            annualRatePercent: (float) $product->interest_rate,
            interestMethod: $product->interest_method,
            termMonths: (int) $request->input('term_months'),
            repaymentFrequency: $product->repayment_frequency,
            processingFeeRatePercent: (float) $product->processing_fee_rate,
            startsOn: today(),
        ));
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
            'payments.reversedBy',
            'payments.allocations.installment',
            'events.user',
            'notificationLogs',
        ]);

        $actor = $request->user();
        $currentTab = $request->string('tab')->toString();
        $currentTab = in_array($currentTab, ['overview', 'schedule', 'payments', 'disbursements', 'notifications', 'activity'], true)
            ? $currentTab
            : 'overview';

        $paymentMethod = PaymentMethod::tryFrom($request->string('payment_method')->toString());
        $paymentFrom = $this->dateFilter($request->string('payment_from')->toString());
        $paymentTo = $this->dateFilter($request->string('payment_to')->toString());

        $payments = $loan->payments
            ->when($paymentMethod !== null, fn (Collection $payments): Collection => $payments
                ->filter(fn (Payment $payment): bool => $payment->method === $paymentMethod))
            ->when($paymentFrom !== null, fn (Collection $payments): Collection => $payments
                ->filter(fn (Payment $payment): bool => $payment->payment_date->toDateString() >= $paymentFrom))
            ->when($paymentTo !== null, fn (Collection $payments): Collection => $payments
                ->filter(fn (Payment $payment): bool => $payment->payment_date->toDateString() <= $paymentTo));

        return Inertia::render('admin/loans/show', [
            'loan' => $this->toPayload($loan),
            'installments' => $this->installmentPayload($loan),
            'disbursements' => $this->disbursementPayload($loan),
            'payments' => $this->paymentPayload($payments, $actor, $loan),
            'paymentCount' => $loan->payments->count(),
            'paymentFilters' => [
                'method' => $paymentMethod?->value,
                'from' => $paymentFrom,
                'to' => $paymentTo,
            ],
            'totals' => $this->paymentTotals($loan),
            'events' => $this->eventPayload($loan),
            'notifications' => $this->notificationPayload($loan, $actor),
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
                'download_statement' => $actor->can('view', $loan),
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
     * Approve the selected pending loans.
     */
    public function approveBulk(ApproveLoansRequest $request, LoanService $loanService): RedirectResponse
    {
        $ids = $request->validated('loans');
        $actor = $request->user();

        $loans = Loan::query()
            ->whereIn('id', $ids)
            ->where('status', LoanStatus::Pending)
            ->get();

        $approved = 0;
        $skipped = count($ids) - $loans->count();

        DB::transaction(function () use ($loans, $actor, $loanService, &$approved): void {
            foreach ($loans as $loan) {
                $loanService->approve($loan, $actor);
                $approved++;
            }
        });

        $message = trans_choice(
            '{0} No loans were approved.|{1} :count loan approved.|[2,*] :count loans approved.',
            $approved,
            ['count' => $approved],
        );

        if ($skipped > 0) {
            $message .= ' '.__(':count skipped.', ['count' => $skipped]);
        }

        Inertia::flash('toast', [
            'type' => $approved > 0 ? 'success' : 'warning',
            'message' => $message,
        ]);

        return back();
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
     * Show a printable receipt for a payment recorded against the loan.
     */
    public function receipt(Loan $loan, Payment $payment): Response
    {
        Gate::authorize('view', $loan);

        abort_unless((int) $payment->loan_id === (int) $loan->id, 404);

        $payment->load(['loan.borrower', 'loan.loanProduct', 'allocations.installment', 'recordedBy', 'reversedBy']);

        return Inertia::render('admin/loans/receipt', [
            'organisation' => app(Settings::class)->organisationName(),
            'receipt' => $this->receiptPayload($payment),
        ]);
    }

    /**
     * Email the receipt for a payment again, even when the original receipt
     * was already sent.
     */
    public function emailReceipt(Loan $loan, Payment $payment, NotificationService $notificationService): RedirectResponse
    {
        Gate::authorize('view', $loan);

        abort_unless((int) $payment->loan_id === (int) $loan->id, 404);

        if ($payment->reversed_at !== null) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('This payment was reversed. Its receipt cannot be emailed.'),
            ]);

            return back();
        }

        $loan->loadMissing(['installments.allocations', 'borrower']);
        $payment->loadMissing('loan.borrower');

        $borrower = $payment->loan->borrower;
        $sent = $notificationService->sendReceipt(
            $payment,
            (float) $this->paymentTotals($loan)['outstanding'],
            force: true,
        );

        Inertia::flash('toast', match (true) {
            $sent => [
                'type' => 'success',
                'message' => __('Receipt emailed to :email.', ['email' => $borrower->email]),
            ],
            blank($borrower->email) => [
                'type' => 'info',
                'message' => __('The borrower has no email address.'),
            ],
            default => [
                'type' => 'info',
                'message' => __('The borrower has opted out of receipt emails.'),
            ],
        });

        return back();
    }

    /**
     * Reverse a recorded payment and restore the outstanding schedule.
     */
    public function reversePayment(
        ReversePaymentRequest $request,
        Loan $loan,
        Payment $payment,
        RepaymentService $repaymentService,
    ): RedirectResponse {
        abort_unless((int) $payment->loan_id === (int) $loan->id, 404);

        $repaymentService->reversePayment(
            $loan,
            $payment,
            $request->user(),
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment reversed.')]);

        return to_route('admin.loans.show', ['loan' => $loan, 'tab' => 'payments']);
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
     * Render the statement email without sending it, for the preview dialog.
     */
    public function previewStatement(Loan $loan): JsonResponse
    {
        Gate::authorize('view', $loan);

        $loan->loadMissing('borrower');

        $borrower = $loan->borrower;

        if ($borrower === null || ! $borrower->wantsEmail(NotificationLog::TYPE_STATEMENT)) {
            return response()->json([
                'eligible' => false,
                'to' => $borrower?->email,
                'subject' => null,
                'html' => null,
                'message' => __('Add an email address and enable statements for this borrower.'),
            ]);
        }

        $message = (new LoanStatement($loan))->toMail($borrower);

        $html = (string) app(Markdown::class)->render($message->markdown, $message->viewData);

        return response()->json([
            'eligible' => true,
            'to' => $borrower->email,
            'subject' => $message->subject,
            'html' => $html,
        ]);
    }

    /**
     * Show a printable loan statement that can be saved as a PDF.
     */
    public function printStatement(Request $request, Loan $loan): Response
    {
        Gate::authorize('view', $loan);

        $loan->load([
            'borrower',
            'loanProduct',
            'installments.allocations',
            'payments.recordedBy',
            'payments.reversedBy',
            'payments.allocations',
        ]);

        return Inertia::render('admin/loans/statement/print', [
            'organisation' => app(Settings::class)->organisationName(),
            'generated_at' => Date::now()->toIso8601String(),
            'loan' => $this->toPayload($loan),
            'borrower' => [
                'name' => $loan->borrower->name,
                'phone' => $loan->borrower->phone,
                'email' => $loan->borrower->email,
                'national_id' => $loan->borrower->national_id,
            ],
            'installments' => $this->installmentPayload($loan),
            'payments' => $this->paymentPayload($loan->payments, $request->user(), $loan),
            'totals' => $this->paymentTotals($loan),
        ]);
    }

    /**
     * Download the loan statement as a CSV, including the schedule and
     * payments, without requiring an email address.
     */
    public function downloadStatement(Loan $loan, CsvExporter $csvExporter): StreamedResponse
    {
        Gate::authorize('view', $loan);

        $loan->load([
            'borrower',
            'loanProduct',
            'installments.allocations',
            'payments.recordedBy',
            'payments.reversedBy',
        ]);

        $loanData = $this->toPayload($loan);
        $totals = $this->paymentTotals($loan);

        $rows = [
            ['Loan statement'],
            ['Loan', '#'.$loanData['id']],
            ['Borrower', $loanData['borrower_name']],
            ['Product', $loanData['loan_product_name']],
            ['Status', $loanData['status_label']],
            ['Principal', $loanData['principal']],
            ['Total payable', $totals['payable']],
            ['Paid', $totals['paid']],
            ['Outstanding', $totals['outstanding']],
            ['Arrears', $totals['arrears']],
            ['Generated', Date::now()->toDateTimeString()],
            [],
            ['Schedule'],
            [
                'Installment',
                'Due date',
                'Principal due',
                'Interest due',
                'Fee due',
                'Penalty accrued',
                'Paid',
                'Outstanding',
                'Status',
            ],
        ];

        foreach ($this->installmentPayload($loan) as $installment) {
            $status = (float) $installment['outstanding'] <= 0.0
                ? 'Paid'
                : ($installment['overdue'] ? 'Overdue' : 'Due');

            $rows[] = [
                $installment['sequence'],
                Date::parse($installment['due_date'])->toDateString(),
                $installment['principal_due'],
                $installment['interest_due'],
                $installment['fee_due'],
                $installment['penalty_accrued'],
                $installment['paid'],
                $installment['outstanding'],
                $status,
            ];
        }

        $rows[] = [];
        $rows[] = ['Payments'];
        $rows[] = ['Date', 'Amount', 'Method', 'Reference', 'Status', 'Recorded by'];

        foreach ($loan->payments->sortBy('payment_date') as $payment) {
            $rows[] = [
                $payment->payment_date->toDateString(),
                number_format((float) $payment->amount, 2, '.', ''),
                $payment->method->label(),
                $payment->reference,
                $payment->reversed_at !== null ? 'Reversed' : 'Posted',
                $payment->recordedBy?->name,
            ];
        }

        return $csvExporter->stream("loan-{$loan->id}-statement.csv", $rows);
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
     * Re-queue an email from the loan's notification log.
     */
    public function resendNotification(
        Loan $loan,
        NotificationLog $notificationLog,
        NotificationService $notificationService,
    ): RedirectResponse {
        Gate::authorize('view', $loan);

        abort_unless((int) $notificationLog->loan_id === (int) $loan->id, 404);

        $resent = $notificationService->resend($notificationLog);

        Inertia::flash('toast', [
            'type' => $resent ? 'success' : 'warning',
            'message' => $resent
                ? __('Notification queued again.')
                : __('The notification could not be resent. Check its delivery status for details.'),
        ]);

        return back();
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
    /**
     * Transform the given payments into the payload shared with the client.
     *
     * @param  Collection<int, Payment>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function paymentPayload(Collection $payments, User $actor, Loan $loan): array
    {
        return $payments
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
                'reversed_at' => $payment->reversed_at?->toIso8601String(),
                'reversal_reason' => $payment->reversal_reason,
                'reversed_by' => $payment->reversedBy?->name,
                'can_reverse' => $actor->can('reversePayment', [$loan, $payment]),
                'allocations' => $this->allocationPayload($payment),
            ])
            ->values()
            ->all();
    }

    /**
     * Transform a payment's allocations into the payload shared by the loan
     * file and the receipt.
     *
     * @return array<int, array<string, mixed>>
     */
    private function allocationPayload(Payment $payment): array
    {
        return $payment->allocations
            ->map(fn (LoanPaymentAllocation $allocation): array => [
                'installment_sequence' => $allocation->sequence,
                'principal_allocated' => $allocation->principal_allocated,
                'interest_allocated' => $allocation->interest_allocated,
                'fee_allocated' => $allocation->fee_allocated,
                'penalty_allocated' => $allocation->penalty_allocated,
            ])
            ->values()
            ->all();
    }

    /**
     * Transform a payment into the printable receipt payload.
     *
     * @return array<string, mixed>
     */
    private function receiptPayload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'number' => 'R-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            'payment_date' => $payment->payment_date->toDateString(),
            'amount' => $payment->amount,
            'method_label' => $payment->method->label(),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'recorded_by' => $payment->recordedBy?->name,
            'reversed_at' => $payment->reversed_at?->toIso8601String(),
            'reversal_reason' => $payment->reversal_reason,
            'reversed_by' => $payment->reversedBy?->name,
            'borrower_name' => $payment->loan->borrower->name,
            'borrower_email' => $payment->loan->borrower->email,
            'loan_id' => $payment->loan_id,
            'product_name' => $payment->loan->loanProduct->name,
            'allocations' => $this->allocationPayload($payment),
        ];
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
        $paid = round((float) $loan->payments->whereNull('reversed_at')->sum('amount'), 2);
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
    private function notificationPayload(Loan $loan, User $actor): array
    {
        return $loan->notificationLogs
            ->sortByDesc(fn (NotificationLog $log) => $log->sent_at ?? $log->created_at)
            ->map(fn (NotificationLog $log): array => [
                'id' => $log->id,
                'type' => $log->type,
                'type_label' => NotificationLog::label($log->type),
                'channel' => $log->channel,
                'channel_label' => NotificationLog::channelLabel($log->channel),
                'status' => $log->status,
                'status_label' => NotificationLog::statusLabel($log->status),
                'error' => $log->error,
                'sent_at' => $log->sent_at?->toIso8601String(),
                'can_resend' => $actor->can('view', $loan)
                    && $loan->borrower->wantsChannel($log->type, $log->channel),
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
     * Accept a `Y-m-d` filter value, ignoring anything else.
     */
    private function dateFilter(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
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
                'interest_method' => $loanProduct->interest_method->value,
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
