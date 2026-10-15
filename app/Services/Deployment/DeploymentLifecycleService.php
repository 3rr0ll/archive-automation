<?php

namespace App\Services\Deployment;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use App\Jobs\DeployArchivedWebsite;
use App\Jobs\WaitForWaybackArchive;
use App\Models\Deployment;
use App\Models\User;
use App\Services\Logging\AuditLogger;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DeploymentLifecycleService
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{server_id: int, domain: string, version: string, archive_url?: string|null, archive?: UploadedFile|null}  $data
     */
    public function submit(User $user, array $data): Deployment
    {
        $upload = $data['archive'] ?? null;
        $isUpload = $upload instanceof UploadedFile;
        $storedPath = null;

        try {
            $deployment = DB::transaction(function () use ($user, $data, $upload, $isUpload, &$storedPath): Deployment {
                $step = $isUpload ? 'archive_ready' : 'waiting_for_archive';

                $deployment = Deployment::create([
                    'user_id' => $user->id,
                    'server_id' => $data['server_id'],
                    'domain' => $data['domain'],
                    'version' => $data['version'],
                    'archive_source' => $isUpload ? 'upload' : 'url',
                    'archive_url' => $isUpload ? null : ($data['archive_url'] ?? null),
                    'archive_original_name' => $isUpload ? $upload->getClientOriginalName() : null,
                    'archive_status' => $isUpload ? ArchiveStatus::Ready : ArchiveStatus::Waiting,
                    'status' => DeploymentStatus::Pending,
                    'current_step' => $step,
                ]);

                if ($isUpload) {
                    $storedPath = $upload->storeAs(
                        "deployments/{$deployment->id}",
                        'archive.zip',
                        'local',
                    );

                    if (! is_string($storedPath)) {
                        throw new RuntimeException('The uploaded ZIP could not be saved.');
                    }

                    $deployment->update(['archive_path' => $storedPath]);
                }

                $deployment->logs()->create([
                    'step_name' => $step,
                    'status' => $isUpload ? 'success' : 'in_progress',
                    'message' => $isUpload
                        ? 'Uploaded ZIP stored privately. Recovery deployment queued.'
                        : 'Recovery submitted. Waiting for the Wayback ZIP to become available.',
                ]);

                return $deployment;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        if ($isUpload) {
            DeployArchivedWebsite::dispatch($deployment);
        } else {
            WaitForWaybackArchive::dispatch($deployment);
        }

        $this->auditLogger->record('deployment.submitted', $deployment, [
            'user_id' => $user->id,
            'server_id' => $deployment->server_id,
            'domain' => $deployment->domain,
            'archive_source' => $deployment->archive_source,
        ]);

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

        $this->auditLogger->record('deployment.cancelled', $deployment, [
            'domain' => $deployment->domain,
        ]);

        return $deployment->refresh();
    }

    public function retry(Deployment $deployment): Deployment
    {
        if (! in_array($deployment->status, [DeploymentStatus::Failed, DeploymentStatus::Cancelled], true)) {
            throw new DomainException('Only failed or cancelled deployments can be retried.');
        }

        $waitsForArchive = filled($deployment->archive_url);

        if ($deployment->archive_source === 'upload'
            && (blank($deployment->archive_path) || ! Storage::disk('local')->exists($deployment->archive_path))) {
            throw new DomainException('The uploaded ZIP is no longer available. Upload it again before retrying.');
        }

        $readyStep = $deployment->archive_source === 'upload' ? 'archive_ready' : 'queued';

        DB::transaction(function () use ($deployment, $waitsForArchive, $readyStep): void {
            $deployment->update([
                'archive_status' => $waitsForArchive ? ArchiveStatus::Waiting : ArchiveStatus::Ready,
                'status' => DeploymentStatus::Pending,
                'current_step' => $waitsForArchive ? 'waiting_for_archive' : $readyStep,
            ]);
            $deployment->logs()->create([
                'step_name' => $waitsForArchive ? 'waiting_for_archive' : $readyStep,
                'status' => 'in_progress',
                'message' => $waitsForArchive
                    ? 'Recovery retry queued. Waiting for the Wayback ZIP.'
                    : 'Deployment retry queued by an administrator.',
            ]);
        });

        $this->auditLogger->record('deployment.retried', $deployment, [
            'domain' => $deployment->domain,
            'waits_for_archive' => $waitsForArchive,
        ]);

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

        $archivePath = $deployment->archive_path;
        $deployment->delete();
        $this->auditLogger->record('deployment.deleted', $deployment, [
            'domain' => $deployment->domain,
            'status' => $deployment->status->value,
        ]);

        if (filled($archivePath)) {
            Storage::disk('local')->delete($archivePath);
            Storage::disk('local')->deleteDirectory("deployments/{$deployment->id}");
        }
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
