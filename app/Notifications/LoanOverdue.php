<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LoanOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, LoanInstallment>  $installments
     */
    public function __construct(public Loan $loan, public Collection $installments) {}

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
        $borrower = $this->loan->borrower;
        $outstanding = round($this->installments->sum(
            fn (LoanInstallment $installment): float => $installment->outstandingTotal(),
        ), 2);

        $daysPastDue = (int) $this->installments->max(
            fn (LoanInstallment $installment): int => $installment->daysPastDue(),
        );

        return (new MailMessage)
            ->subject(__('Overdue balance on loan #:id', ['id' => $this->loan->id]))
            ->greeting(__('Hello :name,', ['name' => $borrower->name]))
            ->line(__('Your loan has :count overdue installment(s).', [
                'count' => $this->installments->count(),
            ]))
            ->line(__('Total overdue: :amount', [
                'amount' => number_format($outstanding, 2, '.', ''),
            ]))
            ->line(__('The oldest unpaid installment is :days day(s) past due.', [
                'days' => $daysPastDue,
            ]))
            ->line(__('Penalties may continue to accrue until the balance is settled.'))
            ->line(__('Please contact us to arrange payment.'));
    }
}
