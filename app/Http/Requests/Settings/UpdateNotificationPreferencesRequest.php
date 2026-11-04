<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notify_due_soon' => ['required', 'boolean'],
            'notify_overdue' => ['required', 'boolean'],
            'notify_receipts' => ['required', 'boolean'],
            'notify_statements' => ['required', 'boolean'],
            'notify_sms' => ['required', 'boolean'],
        ];
    }
}
