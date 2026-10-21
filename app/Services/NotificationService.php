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
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Throwable;

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

            $log = NotificationLog::reserve($key, NotificationLog::TYPE_DUE_SOON, $borrower, $installment->loan);

            if ($log === null) {
                continue;
            }

            try {
                $borrower->notify((new LoanDueSoon($installment))->forLog($log));

                $sent++;
            } catch (Throwable $exception) {
                $log->markFailed($exception->getMessage());
                report($exception);
            }
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

        $log = NotificationLog::reserve($key, NotificationLog::TYPE_OVERDUE, $borrower, $loan);

        if ($log === null) {
            return false;
        }

        try {
            $borrower->notify((new LoanOverdue($loan, $overdue))->forLog($log));

            return true;
        } catch (Throwable $exception) {
            $log->markFailed($exception->getMessage());
            report($exception);

            return false;
        }
    }

    /**
     * Email a receipt for the given payment with the balance left afterwards.
     * Pass `$force` when staff explicitly re-send a receipt so the once-only
     * key does not suppress the new email.
     */
    public function sendReceipt(Payment $payment, float $remainingOutstanding, bool $force = false): bool
    {
        $payment->loadMissing('loan.borrower');

        $borrower = $payment->loan?->borrower;

        if ($borrower === null || ! $borrower->wantsEmail(NotificationLog::TYPE_RECEIPT)) {
            return false;
        }

        $key = $force
            ? sprintf('payment:%d:receipt:%s', $payment->id, Str::uuid())
            : sprintf('payment:%d:receipt', $payment->id);

        $log = NotificationLog::reserve($key, NotificationLog::TYPE_RECEIPT, $borrower, $payment->loan);

        if ($log === null) {
            return false;
        }

        try {
            $borrower->notify((new PaymentReceived($payment, round(max($remainingOutstanding, 0.0), 2)))->forLog($log));

            return true;
        } catch (Throwable $exception) {
            $log->markFailed($exception->getMessage());
            report($exception);

            return false;
        }
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

        $log = NotificationLog::reserve($key, NotificationLog::TYPE_STATEMENT, $borrower, $loan);

        if ($log === null) {
            return false;
        }

        try {
            $borrower->notify((new LoanStatement($loan))->forLog($log));

            return true;
        } catch (Throwable $exception) {
            $log->markFailed($exception->getMessage());
            report($exception);

            return false;
        }
    }

    /**
     * Re-queue a notification from the delivery log using its original source.
     */
    public function resend(NotificationLog $log): bool
    {
        $log->loadMissing(['borrower', 'loan']);

        $borrower = $log->borrower;

        if ($borrower === null || $log->loan === null) {
            $log->markFailed(__('The notification source is no longer available.'));

            return false;
        }

        if (! $borrower->wantsEmail($log->type)) {
            $log->markSkipped(__('The borrower has disabled this email type.'));

            return false;
        }

        $notification = $this->notificationFor($log);

        if ($notification === null) {
            if ($log->status !== NotificationLog::STATUS_SKIPPED) {
                $log->markFailed(__('The notification source is no longer available.'));
            }

            return false;
        }

        $log->markQueued();

        try {
            $borrower->notify($notification->forLog($log));

            return true;
        } catch (Throwable $exception) {
            $log->markFailed($exception->getMessage());
            report($exception);

            return false;
        }
    }

    /**
     * Rebuild the original notification from the log's type and source key.
     */
    private function notificationFor(NotificationLog $log): ?Notification
    {
        return match ($log->type) {
            NotificationLog::TYPE_DUE_SOON => $this->dueSoonNotification($log),
            NotificationLog::TYPE_OVERDUE => $this->overdueNotification($log),
            NotificationLog::TYPE_RECEIPT => $this->receiptNotification($log),
            NotificationLog::TYPE_STATEMENT => $this->statementNotification($log),
            default => null,
        };
    }

    /**
     * Rebuild a due-soon reminder when the installment is still outstanding.
     */
    private function dueSoonNotification(NotificationLog $log): ?Notification
    {
        $installment = LoanInstallment::query()
            ->with(['loan.borrower', 'allocations'])
            ->find($this->subjectId($log));

        if ($installment?->loan === null) {
            return null;
        }

        if ($installment->outstandingTotal() <= 0) {
            $log->markSkipped(__('The installment has already been paid.'));

            return null;
        }

        return new LoanDueSoon($installment);
    }

    /**
     * Rebuild an overdue notice when the loan still has overdue installments.
     */
    private function overdueNotification(NotificationLog $log): ?Notification
    {
        $loan = $log->loan;

        if ($loan === null) {
            return null;
        }

        $loan->loadMissing(['borrower', 'installments.allocations']);

        $overdue = $loan->installments
            ->filter(fn (LoanInstallment $installment): bool => $installment->isOverdue())
            ->sortBy('sequence')
            ->values();

        if ($overdue->isEmpty()) {
            $log->markSkipped(__('The loan no longer has overdue installments.'));

            return null;
        }

        return new LoanOverdue($loan, $overdue);
    }

    /**
     * Rebuild a receipt with the loan's current outstanding balance.
     */
    private function receiptNotification(NotificationLog $log): ?Notification
    {
        $payment = Payment::query()
            ->with(['loan.borrower', 'loan.installments.allocations'])
            ->find($this->subjectId($log));

        if ($payment?->loan === null) {
            return null;
        }

        if ($payment->reversed_at !== null) {
            $log->markSkipped(__('The payment was reversed.'));

            return null;
        }

        $remaining = $payment->loan->installments->sum(
            fn (LoanInstallment $installment): float => $installment->outstandingTotal(),
        );

        return new PaymentReceived($payment, round(max($remaining, 0.0), 2));
    }

    /**
     * Rebuild a statement for the loan.
     */
    private function statementNotification(NotificationLog $log): ?Notification
    {
        $loan = $log->loan;

        if ($loan === null) {
            return null;
        }

        $loan->loadMissing(['borrower', 'loanProduct', 'installments.allocations']);

        return new LoanStatement($loan);
    }

    /**
     * Read the installment or payment id from a key such as
     * `installment:12:due_soon:2026-10-21` or `payment:8:receipt`.
     */
    private function subjectId(NotificationLog $log): ?int
    {
        $parts = explode(':', $log->key);

        return isset($parts[1]) && ctype_digit($parts[1])
            ? (int) $parts[1]
            : null;
    }

    /**
     * @return array<int, string>
     */
    private function openStatusValues(): array
    {
        return array_map(fn (LoanStatus $status): string => $status->value, self::OPEN_STATUSES);
    }
}
