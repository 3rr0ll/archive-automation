<?php

namespace App\Jobs;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Services\InternetArchive\InternetArchiveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class WaitForWaybackArchive implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public function __construct(
        public Deployment $deployment
    ) {}

    public function handle(InternetArchiveService $archiveService): void
    {
        $this->deployment->refresh();

        if ($this->deployment->status === DeploymentStatus::Cancelled
            || $this->deployment->archive_status === ArchiveStatus::Ready
            || $this->deployment->current_step !== 'waiting_for_archive') {
            return;
        }

        if (! $archiveService->isArchiveReady((string) $this->deployment->archive_url)) {
            $this->release(max(1, (int) config('services.internet_archive.poll_delay', 60)));

            return;
        }

        $claimed = Deployment::query()
            ->whereKey($this->deployment->getKey())
            ->where('archive_status', ArchiveStatus::Waiting->value)
            ->where('current_step', 'waiting_for_archive')
            ->update([
                'archive_status' => ArchiveStatus::Ready,
                'current_step' => 'archive_ready',
            ]);

        if ($claimed === 0) {
            return;
        }

        $this->deployment->refresh();
        $this->deployment->logs()->create([
            'step_name' => 'archive_ready',
            'status' => 'success',
            'message' => 'The Wayback ZIP is ready. Recovery deployment queued.',
        ]);

        DeployArchivedWebsite::dispatch($this->deployment);
    }

    public function tries(): int
    {
        return max(1, (int) config('services.internet_archive.max_attempts', 1440));
    }

    public function failed(?Throwable $exception): void
    {
        $this->deployment->refresh();

        if ($this->deployment->status === DeploymentStatus::Cancelled
            || $this->deployment->archive_status === ArchiveStatus::Ready) {
            return;
        }

        $this->deployment->update([
            'archive_status' => ArchiveStatus::Failed,
            'status' => DeploymentStatus::Failed,
            'current_step' => 'archive_wait_failed',
        ]);
        $this->deployment->logs()->create([
            'step_name' => 'archive_wait_failed',
            'status' => 'error',
            'message' => 'The Wayback ZIP did not become available before polling stopped.',
        ]);
    }
}
