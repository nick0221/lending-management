<?php

namespace App\Http\Requests\Admin;

use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Loan::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'borrower_id' => ['required', 'integer', Rule::exists('borrowers', 'id')],
            'loan_product_id' => ['required', 'integer', Rule::exists('loan_products', 'id')],
            'principal' => ['required', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Validate the requested terms against the selected product.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $product = LoanProduct::find($this->integer('loan_product_id'));

            if ($product === null) {
                return;
            }

            if (! $product->is_active) {
                $validator->errors()->add('loan_product_id', __('The selected loan product is not active.'));
            }

            $principal = (float) $this->input('principal');

            if ($principal < (float) $product->min_amount || $principal > (float) $product->max_amount) {
                $validator->errors()->add('principal', __('The principal must be between :min and :max for this product.', [
                    'min' => $product->min_amount,
                    'max' => $product->max_amount,
                ]));
            }

            $termMonths = (int) $this->input('term_months');

            if ($termMonths < $product->min_term_months || $termMonths > $product->max_term_months) {
                $validator->errors()->add('term_months', __('The term must be between :min and :max months for this product.', [
                    'min' => $product->min_term_months,
                    'max' => $product->max_term_months,
                ]));
            }
        });
    }
}
