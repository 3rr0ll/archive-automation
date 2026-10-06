<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServerRequest extends FormRequest
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
            'server_id' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('servers', 'server_id')->ignore($this->route('server')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'ip_address' => ['sometimes', 'required', 'ip'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
