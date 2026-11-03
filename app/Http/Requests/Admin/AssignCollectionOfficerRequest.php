<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCollectionOfficerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $loan = $this->route('loan');

        return $loan instanceof Loan
            && ($this->user()?->can('assignOfficer', $loan) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
