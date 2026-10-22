<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'sms_driver' => ['required', 'in:log,http'],
            'sms_from' => ['nullable', 'string', 'max:64'],
            'sms_http_url' => ['nullable', 'required_if:sms_driver,http', 'url:http,https', 'max:2048'],
            'sms_http_method' => ['required', 'in:POST,PUT,PATCH'],
            'sms_http_format' => ['required', 'in:json,form'],
            'sms_http_token' => ['nullable', 'string', 'max:2048'],
            'sms_http_clear_token' => ['sometimes', 'boolean'],
            'sms_http_token_header' => ['required', 'string', 'max:100'],
            'sms_http_token_prefix' => ['nullable', 'string', 'max:100'],
            'sms_http_headers' => ['nullable', 'string', 'max:5000', 'json'],
            'sms_http_to_field' => ['required', 'string', 'max:50'],
            'sms_http_message_field' => ['required', 'string', 'max:50'],
            'sms_http_from_field' => ['required', 'string', 'max:50'],
            'sms_http_timeout' => ['required', 'integer', 'min:1', 'max:60'],
            'sms_http_connect_timeout' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    /**
     * Validate that custom gateway headers are a JSON object of strings.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $headers = $this->input('sms_http_headers');

            if (! is_string($headers) || trim($headers) === '') {
                return;
            }

            $decoded = json_decode($headers, true);

            if (! is_array($decoded)) {
                $validator->errors()->add(
                    'sms_http_headers',
                    __('The custom headers must be a JSON object of header names and values.'),
                );

                return;
            }

            if ($decoded !== [] && array_is_list($decoded)) {
                $validator->errors()->add(
                    'sms_http_headers',
                    __('The custom headers must be a JSON object of header names and values.'),
                );

                return;
            }

            foreach ($decoded as $name => $value) {
                if (! is_string($name) || trim($name) === '' || ! is_string($value)) {
                    $validator->errors()->add(
                        'sms_http_headers',
                        __('Every custom header must have a string name and value.'),
                    );

                    return;
                }
            }
        });
    }
}
