<?php

namespace App\Jobs;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Services\InternetArchive\InternetArchiveService;
use App\Services\InternetArchive\InternetArchiveSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class WaitForWaybackArchive implements ShouldQueue
{
    use Queueable;

    public int $timeout = 150;

    public function __construct(
        public Deployment $deployment
    ) {}

    public function handle(InternetArchiveService $archiveService): void
    {
        $context = [
            'deployment_id' => $this->deployment->id,
            'domain' => $this->deployment->domain,
            'job' => self::class,
            'job_attempt' => $this->attempts(),
        ];
        Log::shareContext($context);

        try {
            $this->handlePolling($archiveService);
        } finally {
            Log::withoutContext(array_keys($context));
        }
    }

    private function handlePolling(InternetArchiveService $archiveService): void
    {
        $this->deployment->refresh();

        if ($this->deployment->status === DeploymentStatus::Cancelled
            || $this->deployment->archive_status === ArchiveStatus::Ready
            || $this->deployment->current_step !== 'waiting_for_archive') {
            return;
        }

        if (blank($this->deployment->archive_url)) {
            $snapshot = $archiveService->findLatestSnapshot($this->deployment->domain);
            if ($snapshot !== null && ! empty($snapshot['url'])) {
                $this->deployment->update(['archive_url' => $snapshot['url']]);
                $this->deployment->refresh();
                $this->deployment->logs()->create([
                    'step_name' => 'waiting_for_archive',
                    'status' => 'in_progress',
                    'message' => 'Found snapshot on Wayback Machine: '.$snapshot['url'],
                ]);
            } else {
                $this->release(app(InternetArchiveSettings::class)->all()['poll_delay']);

                return;
            }
        }

        if (! $archiveService->isArchiveReady((string) $this->deployment->archive_url)) {
            $this->release(app(InternetArchiveSettings::class)->all()['poll_delay']);

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

        Log::info('Wayback archive became ready.');

        DeployArchivedWebsite::dispatch($this->deployment);
    }

    public function tries(): int
    {
        return app(InternetArchiveSettings::class)->all()['max_attempts'];
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

        Log::error('Wayback archive polling exhausted its retries.', [
            'deployment_id' => $this->deployment->id,
            'domain' => $this->deployment->domain,
            'job' => self::class,
            'exception' => $exception?->getMessage(),
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
