<?php

namespace App\Http\Requests;

use App\Enums\DomainVersion;
use App\Services\InternetArchive\InternetArchiveService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeploymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'server_id' => [
                'required',
                'integer',
                Rule::exists('servers', 'server_id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)
                ),
            ],
            'domain' => ['required', 'string', 'max:255'],
            'version' => ['required', Rule::enum(DomainVersion::class)],
            'archive_url' => [
                'required',
                'string',
                'url',
                'starts_with:https://',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! app(InternetArchiveService::class)->isAllowedArchiveUrl((string) $value)) {
                        $fail('The archive URL must use an allowed Wayback Archive host.');
                    }
                },
            ],
        ];
    }
}
