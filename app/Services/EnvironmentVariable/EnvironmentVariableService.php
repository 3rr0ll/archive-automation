<?php

namespace App\Services\EnvironmentVariable;

use App\Models\EnvironmentVariable;
use Illuminate\Database\Eloquent\Collection;

class EnvironmentVariableService
{
    /**
     * Get all environment variables.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return EnvironmentVariable::orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new environment variable.
     *
     * @param array $data
     * @param int|null $userId
     * @return EnvironmentVariable
     */
    public function create(array $data, ?int $userId): EnvironmentVariable
    {
        return EnvironmentVariable::create([
            'key' => $data['key'],
            'value' => $data['value'],
            'type' => $data['type'],
            'environments' => $data['environments'] ?? 'Production',
            'note' => $data['note'] ?? null,
            'user_id' => $userId,
        ]);
    }

    /**
     * Update an existing environment variable.
     *
     * @param EnvironmentVariable $environmentVariable
     * @param array $data
     * @return EnvironmentVariable
     */
    public function update(EnvironmentVariable $environmentVariable, array $data): EnvironmentVariable
    {
        $updateData = [
            'key' => $data['key'],
            'value' => $data['value'],
            'type' => $data['type'],
        ];

        if (array_key_exists('environments', $data)) {
            $updateData['environments'] = $data['environments'] ?? 'Production';
        }

        if (array_key_exists('note', $data)) {
            $updateData['note'] = $data['note'];
        }

        $environmentVariable->update($updateData);

        return $environmentVariable;
    }

    /**
     * Delete an environment variable.
     *
     * @param EnvironmentVariable $environmentVariable
     * @return bool|null
     */
    public function delete(EnvironmentVariable $environmentVariable): ?bool
    {
        return $environmentVariable->delete();
    }
}
