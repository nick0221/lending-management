<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === Role::SuperAdmin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organisation_name' => ['required', 'string', 'max:120'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'default_penalty_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'money_rate_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'reports_rate_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'reminders_rate_limit' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
