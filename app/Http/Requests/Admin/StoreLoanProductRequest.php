<?php

namespace App\Http\Requests\Admin;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Models\LoanProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', LoanProduct::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(LoanProduct::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'interest_method' => ['required', Rule::enum(InterestMethod::class)],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'repayment_frequency' => ['required', Rule::enum(RepaymentFrequency::class)],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'min_term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'max_term_months' => ['required', 'integer', 'gte:min_term_months', 'max:600'],
            'processing_fee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
