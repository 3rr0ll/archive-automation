<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'service' => ['required', 'string', Rule::in(['runcloud', 'cloudflare', 'gsc'])],
            'account_name' => ['nullable', 'string', 'max:255'],
            'token_data' => ['required', 'string'],
        ];
    }
}
