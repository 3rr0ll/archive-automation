<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInternetArchiveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'allowed_hosts' => ['required', 'array', 'min:1', 'max:20'],
            'allowed_hosts.*' => [
                'required',
                'string',
                'distinct:ignore_case',
                'max:253',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $host = trim((string) $value);

                    if (! str_contains($host, '.')
                        || filter_var($host, FILTER_VALIDATE_IP) !== false
                        || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                        $fail('The :attribute must be a valid host name without a scheme or path.');
                    }
                },
            ],
            'timeout' => ['required', 'integer', 'min:1', 'max:120'],
            'poll_delay' => ['required', 'integer', 'min:1', 'max:86400'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
