<?php

namespace App\Http\Requests\Admin;

use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendLoanRemindersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('sendReminders', Loan::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'loans' => ['required', 'array', 'min:1', 'max:50'],
            'loans.*' => ['integer', 'distinct', 'exists:loans,id'],
        ];
    }
}
