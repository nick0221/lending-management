<?php

namespace App\Http\Requests\Admin;

use App\Enums\DisbursementMethod;
use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DisburseLoanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $loan = $this->route('loan');

        return $loan instanceof Loan
            && ($this->user()?->can('disburse', $loan) ?? false);
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
            'method' => ['required', Rule::enum(DisbursementMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'disbursed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Validate the disbursed amount against the approved principal.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $loan = $this->route('loan');

            if (! $loan instanceof Loan) {
                return;
            }

            if ((float) $this->input('amount') > (float) $loan->principal) {
                $validator->errors()->add('amount', __('The disbursed amount may not exceed the approved principal.'));
            }
        });
    }
}
