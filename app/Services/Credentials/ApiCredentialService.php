<?php

namespace App\Services\Credentials;

use App\Models\ApiCredential;
use App\Services\Logging\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ApiCredentialService
{
    private const RESERVED_SERVICES = ['internet_archive'];

    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{user_id?: int, service?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, ApiCredential>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return ApiCredential::query()
            ->whereNotIn('service', self::RESERVED_SERVICES)
            ->when(isset($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['service']), fn ($query) => $query->where('service', $filters['service']))
            ->latest()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array{user_id: int, service: string, account_name?: string|null, token_data: string}  $data
     */
    public function create(array $data): ApiCredential
    {
        $credential = ApiCredential::create([
            'user_id' => $data['user_id'],
            'service' => $data['service'],
            'account_name' => $data['account_name'] ?? null,
            'token_data_encrypted' => $data['token_data'],
        ]);

        $this->auditLogger->record('api-credential.created', $credential, [
            'service' => $credential->service,
            'owner_id' => $credential->user_id,
        ]);

        return $credential;
    }

    public function accessible(ApiCredential $credential): ApiCredential
    {
        $this->ensureAccessible($credential);

        return $credential;
    }

    /**
     * @param  array{user_id?: int, service?: string, account_name?: string|null, token_data?: string}  $data
     */
    public function update(ApiCredential $credential, array $data): ApiCredential
    {
        $this->ensureAccessible($credential);

        $attributes = collect($data)
            ->except('token_data')
            ->all();

        if (array_key_exists('token_data', $data)) {
            $attributes['token_data_encrypted'] = $data['token_data'];
        }

        $credential->update($attributes);

        $this->auditLogger->record('api-credential.updated', $credential, [
            'service' => $credential->service,
            'owner_id' => $credential->user_id,
            'changed_fields' => array_keys($credential->getChanges()),
        ]);

        return $credential->refresh();
    }

    public function delete(ApiCredential $credential): void
    {
        $this->ensureAccessible($credential);
        $credential->delete();
        $this->auditLogger->record('api-credential.deleted', $credential, [
            'service' => $credential->service,
            'owner_id' => $credential->user_id,
        ]);
    }

    private function ensureAccessible(ApiCredential $credential): void
    {
        if (in_array($credential->service, self::RESERVED_SERVICES, true)) {
            throw (new ModelNotFoundException)->setModel(ApiCredential::class, [$credential->getKey()]);
        }
    }
}
