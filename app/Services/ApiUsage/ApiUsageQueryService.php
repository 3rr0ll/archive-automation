<?php

namespace App\Services\ApiUsage;

use App\Models\ApiUsageRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ApiUsageQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters = []): array
    {
        $query = $this->filteredQuery($filters);
        $total = (clone $query)->count();
        $successful = (clone $query)->where('successful', true)->count();
        $deploymentRequests = (clone $query)->whereNotNull('deployment_id')->count();

        $providers = (clone $query)
            ->selectRaw('provider, COUNT(*) as requests')
            ->selectRaw('SUM(CASE WHEN successful = 1 THEN 1 ELSE 0 END) as successful_requests')
            ->selectRaw('SUM(CASE WHEN successful = 0 THEN 1 ELSE 0 END) as failed_requests')
            ->selectRaw('SUM(CASE WHEN deployment_id IS NOT NULL THEN 1 ELSE 0 END) as deployment_requests')
            ->selectRaw('AVG(duration_ms) as average_duration_ms')
            ->selectRaw('MAX(requested_at) as last_requested_at')
            ->groupBy('provider')
            ->orderBy('provider')
            ->get()
            ->map(fn (ApiUsageRecord $record): array => [
                'provider' => $record->provider,
                'requests' => (int) $record->getAttribute('requests'),
                'successful' => (int) $record->getAttribute('successful_requests'),
                'failed' => (int) $record->getAttribute('failed_requests'),
                'deployment_requests' => (int) $record->getAttribute('deployment_requests'),
                'unassigned_requests' => (int) $record->getAttribute('requests') - (int) $record->getAttribute('deployment_requests'),
                'average_duration_ms' => $record->getAttribute('average_duration_ms') === null
                    ? null
                    : round((float) $record->getAttribute('average_duration_ms'), 2),
                'last_requested_at' => $record->getAttribute('last_requested_at'),
            ])->values();

        return [
            'requests' => $total,
            'successful' => $successful,
            'failed' => $total - $successful,
            'deployment_requests' => $deploymentRequests,
            'unassigned_requests' => $total - $deploymentRequests,
            'providers' => $providers,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function operations(array $filters = []): Collection
    {
        return $this->filteredQuery($filters)
            ->selectRaw('provider, operation, COUNT(*) as requests')
            ->selectRaw('SUM(CASE WHEN successful = 1 THEN 1 ELSE 0 END) as successful_requests')
            ->selectRaw('SUM(CASE WHEN successful = 0 THEN 1 ELSE 0 END) as failed_requests')
            ->selectRaw('AVG(duration_ms) as average_duration_ms')
            ->groupBy('provider', 'operation')
            ->orderBy('provider')
            ->orderByDesc('requests')
            ->get()
            ->map(fn (ApiUsageRecord $record): array => [
                'provider' => $record->provider,
                'operation' => $record->operation,
                'requests' => (int) $record->getAttribute('requests'),
                'successful' => (int) $record->getAttribute('successful_requests'),
                'failed' => (int) $record->getAttribute('failed_requests'),
                'average_duration_ms' => $record->getAttribute('average_duration_ms') === null
                    ? null
                    : round((float) $record->getAttribute('average_duration_ms'), 2),
            ]);
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters): Builder
    {
        return ApiUsageRecord::query()
            ->when(isset($filters['deployment_id']), fn (Builder $query) => $query->where('deployment_id', $filters['deployment_id']))
            ->when(isset($filters['provider']), fn (Builder $query) => $query->where('provider', $filters['provider']))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('requested_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('requested_at', '<=', $filters['date_to']));
    }
}
