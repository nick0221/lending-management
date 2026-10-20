<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Notifications\LoanDueSoon;
use App\Notifications\LoanOverdue;
use App\Notifications\LoanStatement;
use App\Notifications\PaymentReceived;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Sends the borrower emails and records each one so scheduled commands stay
 * idempotent when they run more than once on the same day.
 */
class NotificationService
{
    /**
     * Statuses that still carry collection activity.
     *
     * @var array<int, LoanStatus>
     */
    private const OPEN_STATUSES = [LoanStatus::Active, LoanStatus::Defaulted];

    /**
     * Email reminders for installments due in the configured number of days.
     */
    public function sendDueReminders(?CarbonInterface $asOf = null, ?int $days = null): int
    {
        $asOf = ($asOf ?? today())->startOfDay();
        $days ??= (int) config('lending.due_reminder_days', 3);
        $dueOn = $asOf->addDays($days);

        $installments = LoanInstallment::query()
            ->with(['loan.borrower', 'allocations'])
            ->whereDate('due_date', $dueOn->toDateString())
            ->whereHas('loan', fn ($query) => $query->whereIn('status', $this->openStatusValues()))
            ->get();

        $sent = 0;

        foreach ($installments as $installment) {
            $borrower = $installment->loan?->borrower;

            if ($borrower === null || ! $borrower->wantsEmail(NotificationLog::TYPE_DUE_SOON)) {
                continue;
            }

            if ($installment->outstandingTotal() <= 0) {
                continue;
            }

            $key = sprintf('installment:%d:due_soon:%s', $installment->id, $dueOn->toDateString());

            if (! NotificationLog::reserve($key, NotificationLog::TYPE_DUE_SOON, $borrower, $installment->loan)) {
                continue;
            }

            $borrower->notify(new LoanDueSoon($installment));

            $sent++;
        }

        return $sent;
    }

    /**
     * One overdue notice per loan per day, summarising its unpaid installments.
     */
    public function sendOverdueNotices(?CarbonInterface $asOf = null): int
    {
        $asOf = ($asOf ?? today())->startOfDay();

        $loans = Loan::query()
            ->with(['borrower', 'installments.allocations'])
            ->whereIn('status', $this->openStatusValues())
            ->get();

        $sent = 0;

        foreach ($loans as $loan) {
            if ($this->sendOverdueNotice($loan, $asOf)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Email one overdue notice for the loan, summarising its unpaid
     * installments. Returns false when the notice is not applicable or has
     * already gone out today.
     */
    public function sendOverdueNotice(Loan $loan, ?CarbonInterface $asOf = null): bool
    {
        $asOf = ($asOf ?? today())->startOfDay();

        if (! in_array($loan->status, self::OPEN_STATUSES, true)) {
            return false;
        }

        $loan->loadMissing(['borrower', 'installments.allocations']);

        $borrower = $loan->borrower;

        if ($borrower === null || ! $borrower->wantsEmail(NotificationLog::TYPE_OVERDUE)) {
            return false;
        }

        $overdue = $loan->installments
            ->filter(fn (LoanInstallment $installment): bool => $installment->isOverdue())
            ->sortBy('sequence')
            ->values();

        if ($overdue->isEmpty()) {
            return false;
        }

        $key = sprintf('loan:%d:overdue:%s', $loan->id, $asOf->toDateString());

        if (! NotificationLog::reserve($key, NotificationLog::TYPE_OVERDUE, $borrower, $loan)) {
            return false;
        }

        $borrower->notify(new LoanOverdue($loan, $overdue));

        return true;
    }

    /**
     * Email a receipt for the given payment with the balance left afterwards.
     */
    public function sendReceipt(Payment $payment, float $remainingOutstanding): bool
    {
        $payment->loadMissing('loan.borrower');

        $borrower = $payment->loan?->borrower;

        if ($borrower === null || ! $borrower->wantsEmail(NotificationLog::TYPE_RECEIPT)) {
            return false;
        }

        $key = sprintf('payment:%d:receipt', $payment->id);

        if (! NotificationLog::reserve($key, NotificationLog::TYPE_RECEIPT, $borrower, $payment->loan)) {
            return false;
        }

        $borrower->notify(new PaymentReceived($payment, round(max($remainingOutstanding, 0.0), 2)));

        return true;
    }

    /**
     * Email the loan's repayment schedule on request.
     */
    public function sendStatement(Loan $loan): bool
    {
        $loan->loadMissing('borrower');

        $borrower = $loan->borrower;

        if ($borrower === null || ! $borrower->wantsEmail(NotificationLog::TYPE_STATEMENT)) {
            return false;
        }

        $key = sprintf('loan:%d:statement:%s', $loan->id, Str::uuid());

        NotificationLog::reserve($key, NotificationLog::TYPE_STATEMENT, $borrower, $loan);

        $borrower->notify(new LoanStatement($loan));

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function openStatusValues(): array
    {
        return array_map(fn (LoanStatus $status): string => $status->value, self::OPEN_STATUSES);
    }
}
