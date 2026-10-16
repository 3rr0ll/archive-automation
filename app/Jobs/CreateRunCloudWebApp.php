<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\ApiUsage\ApiUsageContext;
use App\Services\RunCloud\RunCloudService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateRunCloudWebApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public Deployment $deployment
    ) {}

    public function handle(RunCloudService $runCloudService, ?ApiUsageContext $apiUsageContext = null): void
    {
        $context = [
            'deployment_id' => $this->deployment->id,
            'domain' => $this->deployment->domain,
            'job' => self::class,
            'job_attempt' => $this->attempts(),
        ];
        Log::shareContext($context);

        try {
            Log::info('RunCloud web application job started.');
            ($apiUsageContext ?? app(ApiUsageContext::class))->forDeployment(
                (int) $this->deployment->getKey(),
                fn () => $runCloudService->createEmptyWebApplication(
                    $this->deployment->domain,
                    (int) $this->deployment->server_id,
                ),
            );
            Log::info('RunCloud web application job finished.');
        } finally {
            Log::withoutContext(array_keys($context));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('RunCloud web application job exhausted its retries.', [
            'deployment_id' => $this->deployment->id,
            'domain' => $this->deployment->domain,
            'job' => self::class,
            'exception' => $exception?->getMessage(),
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
