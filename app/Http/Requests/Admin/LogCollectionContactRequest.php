<?php

namespace App\Http\Requests\Admin;

use App\Enums\CollectionChannel;
use App\Enums\CollectionOutcome;
use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogCollectionContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $loan = $this->route('loan');

        return $loan instanceof Loan
            && ($this->user()?->can('logContact', $loan) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(CollectionChannel::class)],
            'outcome' => ['required', Rule::enum(CollectionOutcome::class)],
            'contacted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'promised_amount' => ['nullable', 'numeric', 'min:0.01', 'required_if:outcome,promised'],
            'promised_at' => ['nullable', 'date', 'required_if:outcome,promised'],
            'follow_up_at' => ['nullable', 'date'],
        ];
    }
}
