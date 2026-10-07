<?php

namespace App\Http\Requests;

use App\Rules\InternetArchiveSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiCredentialRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'service' => ['required', 'string', Rule::in(['runcloud', 'cloudflare', 'gsc', 'internet_archive'])],
            'account_name' => ['nullable', 'string', 'max:255'],
            'token_data' => [
                'required',
                'string',
                Rule::when($this->input('service') === 'internet_archive', [new InternetArchiveSettings]),
            ],
        ];
    }
}
