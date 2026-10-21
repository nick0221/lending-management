<?php

namespace App\Http\Requests\Admin;

use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LoanCalculatorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Loan::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'loan_product_id' => ['nullable', 'integer', Rule::exists('loan_products', 'id')->where('is_active', true)],
            'principal' => ['nullable', 'numeric', 'min:0'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];
    }

    /**
     * Validate the quote against the selected product's limits once all the
     * terms are present.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $keys = ['loan_product_id', 'principal', 'term_months'];
            $filled = array_values(array_filter(
                $keys,
                fn (string $key): bool => $this->filled($key),
            ));

            if ($filled === []) {
                return;
            }

            if (count($filled) < count($keys)) {
                foreach ($keys as $key) {
                    if (! $this->filled($key)) {
                        $validator->errors()->add($key, __('All three terms are required to calculate a quote.'));
                    }
                }

                return;
            }

            $product = LoanProduct::find($this->integer('loan_product_id'));

            if ($product === null) {
                return;
            }

            $principal = (float) $this->input('principal');

            if ($principal < (float) $product->min_amount || $principal > (float) $product->max_amount) {
                $validator->errors()->add('principal', __('The principal must be between :min and :max.', [
                    'min' => number_format((float) $product->min_amount, 2),
                    'max' => number_format((float) $product->max_amount, 2),
                ]));
            }

            $term = (int) $this->input('term_months');

            if ($term < $product->min_term_months || $term > $product->max_term_months) {
                $validator->errors()->add('term_months', __('The term must be between :min and :max months.', [
                    'min' => $product->min_term_months,
                    'max' => $product->max_term_months,
                ]));
            }
        });
    }
}
