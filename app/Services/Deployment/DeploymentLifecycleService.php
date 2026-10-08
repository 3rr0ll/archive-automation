<?php

namespace App\Services\Deployment;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use App\Jobs\DeployArchivedWebsite;
use App\Jobs\WaitForWaybackArchive;
use App\Models\Deployment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeploymentLifecycleService
{
    /**
     * @param  array{server_id: int, domain: string, version: string, archive_url: string}  $data
     */
    public function submit(User $user, array $data): Deployment
    {
        $deployment = DB::transaction(function () use ($user, $data): Deployment {
            $deployment = Deployment::create([
                'user_id' => $user->id,
                'server_id' => $data['server_id'],
                'domain' => $data['domain'],
                'version' => $data['version'],
                'archive_url' => $data['archive_url'],
                'archive_status' => ArchiveStatus::Waiting,
                'status' => DeploymentStatus::Pending,
                'current_step' => 'waiting_for_archive',
            ]);

            $deployment->logs()->create([
                'step_name' => 'waiting_for_archive',
                'status' => 'in_progress',
                'message' => 'Recovery submitted. Waiting for the Wayback ZIP to become available.',
            ]);

            return $deployment;
        });

        WaitForWaybackArchive::dispatch($deployment);

        return $deployment;
    }

    public function cancel(Deployment $deployment): Deployment
    {
        if (! in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Processing], true)) {
            throw new DomainException('Only pending or processing deployments can be cancelled.');
        }

        DB::transaction(function () use ($deployment): void {
            $deployment->update([
                'status' => DeploymentStatus::Cancelled,
                'current_step' => 'cancelled',
            ]);
            $deployment->logs()->create([
                'step_name' => 'cancelled',
                'status' => 'warning',
                'message' => 'Deployment cancelled by an administrator.',
            ]);
        });

        return $deployment->refresh();
    }

    public function retry(Deployment $deployment): Deployment
    {
        if (! in_array($deployment->status, [DeploymentStatus::Failed, DeploymentStatus::Cancelled], true)) {
            throw new DomainException('Only failed or cancelled deployments can be retried.');
        }

        $waitsForArchive = filled($deployment->archive_url);

        DB::transaction(function () use ($deployment, $waitsForArchive): void {
            $deployment->update([
                'archive_status' => $waitsForArchive ? ArchiveStatus::Waiting : $deployment->archive_status,
                'status' => DeploymentStatus::Pending,
                'current_step' => $waitsForArchive ? 'waiting_for_archive' : 'queued',
            ]);
            $deployment->logs()->create([
                'step_name' => $waitsForArchive ? 'waiting_for_archive' : 'queued',
                'status' => 'in_progress',
                'message' => $waitsForArchive
                    ? 'Recovery retry queued. Waiting for the Wayback ZIP.'
                    : 'Deployment retry queued by an administrator.',
            ]);
        });

        if ($waitsForArchive) {
            WaitForWaybackArchive::dispatch($deployment);
        } else {
            DeployArchivedWebsite::dispatch($deployment);
        }

        return $deployment->refresh();
    }

    public function delete(Deployment $deployment): void
    {
        if ($deployment->status === DeploymentStatus::Processing) {
            throw new DomainException('Cancel a processing deployment before deleting it.');
        }

        $deployment->delete();
    }

    /**
     * @param  list<int>  $ids
     * @return array{processed_ids: list<int>, skipped: list<array{id: int, reason: string}>}
     */
    public function bulkCancel(array $ids): array
    {
        return $this->bulk($ids, fn (Deployment $deployment): Deployment => $this->cancel($deployment));
    }

    /**
     * @param  list<int>  $ids
     * @return array{processed_ids: list<int>, skipped: list<array{id: int, reason: string}>}
     */
    public function bulkRetry(array $ids): array
    {
        return $this->bulk($ids, fn (Deployment $deployment): Deployment => $this->retry($deployment));
    }

    /**
     * @param  list<int>  $ids
     * @return array{processed_ids: list<int>, skipped: list<array{id: int, reason: string}>}
     */
    public function bulkDelete(array $ids): array
    {
        return $this->bulk($ids, function (Deployment $deployment): void {
            $this->delete($deployment);
        });
    }

    /**
     * @param  list<int>  $ids
     * @param  callable(Deployment): mixed  $operation
     * @return array{processed_ids: list<int>, skipped: list<array{id: int, reason: string}>}
     */
    private function bulk(array $ids, callable $operation): array
    {
        $deployments = Deployment::query()->whereKey($ids)->get()->keyBy('id');
        $processedIds = [];
        $skipped = [];

        foreach ($ids as $id) {
            $deployment = $deployments->get($id);

            if (! $deployment instanceof Deployment) {
                $skipped[] = ['id' => $id, 'reason' => 'Deployment no longer exists.'];

                continue;
            }

            try {
                $operation($deployment);
                $processedIds[] = $id;
            } catch (DomainException $exception) {
                $skipped[] = ['id' => $id, 'reason' => $exception->getMessage()];
            }
        }

        return ['processed_ids' => $processedIds, 'skipped' => $skipped];
    }
}
