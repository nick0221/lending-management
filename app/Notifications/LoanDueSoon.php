<?php

namespace App\Notifications;

use App\Models\LoanInstallment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanDueSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LoanInstallment $installment) {}

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
        $loan = $this->installment->loan;
        $borrower = $loan->borrower;

        return (new MailMessage)
            ->subject(__('Payment due soon for loan #:id', ['id' => $loan->id]))
            ->greeting(__('Hello :name,', ['name' => $borrower->name]))
            ->line(__('This is a reminder that installment #:sequence of your loan is due on :date.', [
                'sequence' => $this->installment->sequence,
                'date' => $this->installment->due_date->toFormattedDateString(),
            ]))
            ->line(__('Amount due: :amount', [
                'amount' => number_format($this->installment->outstandingTotal(), 2, '.', ''),
            ]))
            ->line(__('If you have already made this payment, please disregard this message.'));
    }
}
