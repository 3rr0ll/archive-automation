<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApiCredentialRequest extends FormRequest
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
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'service' => ['sometimes', 'required', 'string', Rule::in(['runcloud', 'cloudflare', 'gsc'])],
            'account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'token_data' => ['sometimes', 'required', 'string', 'max:65535'],
        ];
    }
}
