<?php

namespace App\Services\Deployment;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use Illuminate\Database\Eloquent\Builder;

class DeploymentStatisticsService
{
    /**
     * @param  Builder<Deployment>  $query
     * @return array{total: int, active: int, status_counts: array<string, int>, archive_status_counts: array<string, int>, current_step_counts: array<string, int>}
     */
    public function calculate(Builder $query): array
    {
        $statusCounts = array_fill_keys(array_column(DeploymentStatus::cases(), 'value'), 0);
        $archiveStatusCounts = array_fill_keys(array_column(ArchiveStatus::cases(), 'value'), 0);

        foreach ((clone $query)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status') as $status => $count) {
            $statusCounts[$status] = (int) $count;
        }

        foreach ((clone $query)->selectRaw('archive_status, COUNT(*) as aggregate')->groupBy('archive_status')->pluck('aggregate', 'archive_status') as $status => $count) {
            if ($status !== null) {
                $archiveStatusCounts[$status] = (int) $count;
            }
        }

        $stepCounts = (clone $query)
            ->selectRaw('current_step, COUNT(*) as aggregate')
            ->whereNotNull('current_step')
            ->groupBy('current_step')
            ->pluck('aggregate', 'current_step')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return [
            'total' => array_sum($statusCounts),
            'active' => $statusCounts[DeploymentStatus::Pending->value]
                + $statusCounts[DeploymentStatus::Processing->value],
            'status_counts' => $statusCounts,
            'archive_status_counts' => $archiveStatusCounts,
            'current_step_counts' => $stepCounts,
        ];
    }
}
