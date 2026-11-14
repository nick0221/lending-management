<?php

namespace App\Http\Requests\Admin;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\Payment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $loan = $this->route('loan');

        if (! $loan instanceof Loan) {
            return false;
        }

        $user = $this->user();

        if ($user?->can('recordPayment', $loan)) {
            return true;
        }

        // A retry of an already-recorded submission is allowed even when the
        // first payment closed the loan and it can no longer take payments.
        $payment = $this->recordedPayment();

        return $payment !== null
            && (int) $payment->loan_id === (int) $loan->id
            && ($user?->can('view', $loan) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $loan = $this->route('loan');

                    if (! is_string($value) || trim($value) === '' || ! $loan instanceof Loan) {
                        return;
                    }

                    // A retried submission already recorded this reference.
                    if ($this->alreadyRecorded()) {
                        return;
                    }

                    $isTaken = $loan->payments()
                        ->where('reference', trim($value))
                        ->whereNull('reversed_at')
                        ->exists();

                    if ($isTaken) {
                        $fail(__('A live payment on this loan already uses this reference.'));
                    }
                },
            ],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'payment_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // A duplicate submission is answered with the original payment.
            if ($this->alreadyRecorded()) {
                return;
            }

            $loan = $this->route('loan');
            if (! $loan instanceof Loan) {
                return;
            }

            if ($loan->status !== LoanStatus::Active) {
                $validator->errors()->add('loan', __('Payments can only be recorded against active loans.'));
            }
        });
    }

    /**
     * Determine whether this submission token was already recorded.
     */
    private function alreadyRecorded(): bool
    {
        return $this->recordedPayment() !== null;
    }

    /**
     * Find the payment recorded by an earlier submission of this form.
     */
    private function recordedPayment(): ?Payment
    {
        $key = $this->input('idempotency_key');

        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        return Payment::query()
            ->where('idempotency_key', trim($key))
            ->first();
    }
}
