<?php

namespace App\Http\Requests\Admin;

use App\Enums\KycStatus;
use App\Models\Borrower;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBorrowerKycRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('updateAny', Borrower::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'borrowers' => ['required', 'array', 'min:1', 'max:100'],
            'borrowers.*' => ['required', 'integer', 'distinct', Rule::exists(Borrower::class, 'id')],
            'kyc_status' => ['required', Rule::enum(KycStatus::class)],
        ];
    }
}
