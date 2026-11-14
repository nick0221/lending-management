<?php

namespace App\Http\Requests\Admin;

use App\Enums\KycStatus;
use App\Models\Borrower;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBorrowerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Borrower::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    /**
     * The rules shared by the create form and the CSV importer.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'national_id' => [
                'nullable',
                'string',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Borrower::onlyTrashed()->where('national_id', $value)->exists()) {
                        $fail(__('This national ID belongs to an archived borrower. Restore that borrower instead of creating a duplicate.'));

                        return;
                    }

                    if (Borrower::query()->where('national_id', $value)->exists()) {
                        $fail(__('This national ID is already registered to another borrower.'));
                    }
                },
            ],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'kyc_status' => ['required', Rule::enum(KycStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notify_due_soon' => ['boolean'],
            'notify_overdue' => ['boolean'],
            'notify_receipts' => ['boolean'],
            'notify_statements' => ['boolean'],
            'notify_sms' => ['boolean'],
        ];
    }
}
