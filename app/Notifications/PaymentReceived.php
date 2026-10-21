<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\TracksNotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable, TracksNotificationLog;

    public function __construct(public Payment $payment, public float $remainingOutstanding) {}

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
        $loan = $this->payment->loan;
        $borrower = $loan->borrower;

        $message = (new MailMessage)
            ->subject(__('Payment received for loan #:id', ['id' => $loan->id]))
            ->greeting(__('Hello :name,', ['name' => $borrower->name]))
            ->line(__('We received your payment of :amount.', [
                'amount' => number_format((float) $this->payment->amount, 2, '.', ''),
            ]))
            ->line(__('Payment date: :date', [
                'date' => $this->payment->payment_date->toFormattedDateString(),
            ]))
            ->line(__('Method: :method', ['method' => $this->payment->method->label()]));

        if (filled($this->payment->reference)) {
            $message->line(__('Reference: :reference', ['reference' => $this->payment->reference]));
        }

        return $message
            ->line(__('Remaining balance: :amount', [
                'amount' => number_format($this->remainingOutstanding, 2, '.', ''),
            ]))
            ->line(__('Thank you.'));
    }
}
