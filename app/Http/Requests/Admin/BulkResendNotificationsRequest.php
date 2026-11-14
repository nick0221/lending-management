<?php

namespace App\Http\Requests\Admin;

use App\Models\NotificationLog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkResendNotificationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', NotificationLog::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logs' => ['required', 'array', 'min:1', 'max:50'],
            'logs.*' => ['integer', 'distinct', 'exists:notification_logs,id'],
        ];
    }
}
