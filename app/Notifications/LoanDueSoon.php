<?php

namespace App\Notifications;

use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use App\Notifications\Concerns\TracksNotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanDueSoon extends Notification implements ShouldQueue
{
    use Queueable, TracksNotificationLog;

    public function __construct(public LoanInstallment $installment) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, NotificationLog::TYPE_DUE_SOON);
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

    /**
     * Build the short text message for the SMS channel.
     */
    public function toSms(object $notifiable): string
    {
        $loan = $this->installment->loan;

        return __('Loan #:id: installment #:sequence of :amount is due :date.', [
            'id' => $loan->id,
            'sequence' => $this->installment->sequence,
            'amount' => number_format($this->installment->outstandingTotal(), 2, '.', ''),
            'date' => $this->installment->due_date->toFormattedDateString(),
        ]);
    }
}
