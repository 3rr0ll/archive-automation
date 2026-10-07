<?php

namespace App\Http\Requests;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminDeploymentIndexRequest extends FormRequest
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
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::enum(DeploymentStatus::class)],
            'archive_status' => ['sometimes', 'string', Rule::enum(ArchiveStatus::class)],
            'server_id' => ['sometimes', 'integer', 'exists:servers,server_id'],
            'search' => ['sometimes', 'string', 'max:255'],
            'current_step' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['sometimes', Rule::in([
                'created_at',
                'updated_at',
                'domain',
                'status',
                'archive_status',
                'current_step',
            ])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
