<?php

namespace App\Services\EnvironmentVariable;

use App\Models\EnvironmentVariable;
use App\Services\Logging\AuditLogger;
use Illuminate\Database\Eloquent\Collection;

class EnvironmentVariableService
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Get all environment variables.
     */
    public function getAll(): Collection
    {
        return EnvironmentVariable::orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new environment variable.
     */
    public function create(array $data, ?int $userId): EnvironmentVariable
    {
        $variable = EnvironmentVariable::create([
            'key' => $data['key'],
            'value' => $data['value'],
            'type' => $data['type'],
            'environments' => $data['environments'] ?? 'Production',
            'note' => $data['note'] ?? null,
            'user_id' => $userId,
        ]);

        $this->auditLogger->record('environment-variable.created', $variable, [
            'key' => $variable->key,
            'type' => $variable->type,
        ]);

        return $variable;
    }

    /**
     * Update an existing environment variable.
     */
    public function update(EnvironmentVariable $environmentVariable, array $data): EnvironmentVariable
    {
        $updateData = [
            'key' => $data['key'],
            'value' => $data['value'],
            'type' => $data['type'],
            'environments' => $data['environments'] ?? $environmentVariable->environments,
            'note' => $data['note'] ?? null,
        ]);
        ];

        if (array_key_exists('environments', $data)) {
            $updateData['environments'] = $data['environments'] ?? 'Production';
        }

        if (array_key_exists('note', $data)) {
            $updateData['note'] = $data['note'];
        }

        $environmentVariable->update($updateData);

        $this->auditLogger->record('environment-variable.updated', $environmentVariable, [
            'key' => $environmentVariable->key,
            'changed_fields' => array_keys($environmentVariable->getChanges()),
        ]);

        return $environmentVariable;
    }

    /**
     * Delete an environment variable.
     */
    public function delete(EnvironmentVariable $environmentVariable): ?bool
    {
        $deleted = $environmentVariable->delete();

        $this->auditLogger->record('environment-variable.deleted', $environmentVariable, [
            'key' => $environmentVariable->key,
            'type' => $environmentVariable->type,
        ]);

        return $deleted;
    }
}
