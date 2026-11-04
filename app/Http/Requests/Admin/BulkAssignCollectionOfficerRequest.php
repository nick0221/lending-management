<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignCollectionOfficerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdminPanel() ?? false;
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
            'officer_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereIn('role', [
                    Role::SuperAdmin->value,
                    Role::Admin->value,
                ]),
            ],
        ];
    }
}
