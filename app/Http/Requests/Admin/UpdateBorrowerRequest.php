<?php

namespace App\Http\Requests\Admin;

use App\Enums\KycStatus;
use App\Models\Borrower;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBorrowerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->targetBorrower()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'national_id' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique(Borrower::class)->ignore($this->targetBorrower()->id),
            ],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'kyc_status' => ['required', Rule::enum(KycStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * Get the borrower being updated.
     */
    private function targetBorrower(): Borrower
    {
        /** @var Borrower $borrower */
        $borrower = $this->route('borrower');

        return $borrower;
    }
}
