<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/**
 * Validate the terms used to preview a schedule. The borrower and notes are
 * not needed until the loan is actually stored, so the store request's
 * product and range checks are reused with a smaller rule set.
 */
class PreviewLoanRequest extends StoreLoanRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loan_product_id' => ['required', 'integer', Rule::exists('loan_products', 'id')],
            'principal' => ['required', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
        ];
    }
}
