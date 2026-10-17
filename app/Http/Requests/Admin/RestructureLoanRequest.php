<?php

namespace App\Http\Requests\Admin;

use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RestructureLoanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $loan = $this->route('loan');

        return $loan instanceof Loan
            && ($this->user()?->can('restructure', $loan) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'starts_on' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $loan = $this->route('loan');

            if (! $loan instanceof Loan) {
                return;
            }

            $term = (int) $this->input('term_months');
            $product = $loan->loanProduct;

            if ($product === null) {
                return;
            }

            if ($term < $product->min_term_months || $term > $product->max_term_months) {
                $validator->errors()->add('term_months', __('The term must be between :min and :max months for this product.', [
                    'min' => $product->min_term_months,
                    'max' => $product->max_term_months,
                ]));
            }
        });
    }
}
