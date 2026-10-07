<?php

namespace App\Http\Requests;

use App\Models\ApiCredential;
use App\Rules\InternetArchiveSettings;
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
        $credential = $this->route('apiCredential');
        $service = $this->input(
            'service',
            $credential instanceof ApiCredential ? $credential->service : null,
        );
        $tokenPresenceRule = $service === 'internet_archive'
            && $credential instanceof ApiCredential
            && $credential->service !== 'internet_archive'
                ? 'required'
                : 'sometimes';

        return [
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'service' => ['sometimes', 'required', 'string', Rule::in(['runcloud', 'cloudflare', 'gsc', 'internet_archive'])],
            'account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'token_data' => [
                $tokenPresenceRule,
                'required',
                'string',
                Rule::when($service === 'internet_archive', [new InternetArchiveSettings]),
            ],
        ];
    }
}
