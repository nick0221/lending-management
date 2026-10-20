<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LoanStatement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Loan $loan) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->loan->loadMissing(['borrower', 'loanProduct', 'installments.allocations']);

        /** @var Collection<int, LoanInstallment> $installments */
        $installments = $this->loan->installments->sortBy('sequence')->values();

        return (new MailMessage)
            ->subject(__('Statement for loan #:id', ['id' => $this->loan->id]))
            ->markdown('mail.loan-statement', [
                'loan' => $this->loan,
                'borrower' => $this->loan->borrower,
                'product' => $this->loan->loanProduct?->name,
                'rows' => $this->rows($installments),
                'totals' => $this->totals($installments),
            ]);
    }

    /**
     * @param  Collection<int, LoanInstallment>  $installments
     * @return array<int, array<string, string|int>>
     */
    private function rows(Collection $installments): array
    {
        return $installments->map(fn (LoanInstallment $installment): array => [
            'sequence' => $installment->sequence,
            'due_date' => $installment->due_date->toDateString(),
            'due' => number_format($installment->dueAmount(), 2, '.', ''),
            'paid' => number_format($installment->dueAmount() - $installment->outstandingDue(), 2, '.', ''),
            'outstanding' => number_format($installment->outstandingTotal(), 2, '.', ''),
        ])->all();
    }

    /**
     * @param  Collection<int, LoanInstallment>  $installments
     * @return array<string, string>
     */
    private function totals(Collection $installments): array
    {
        return [
            'due' => number_format($installments->sum(fn (LoanInstallment $installment): float => $installment->dueAmount()), 2, '.', ''),
            'outstanding' => number_format($installments->sum(fn (LoanInstallment $installment): float => $installment->outstandingTotal()), 2, '.', ''),
        ];
    }
}
