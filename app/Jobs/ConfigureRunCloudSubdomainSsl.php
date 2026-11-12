<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\ApiUsage\ApiUsageContext;
use App\Services\Cloudflare\CloudflareClient;
use App\Services\Cloudflare\CloudflareDnsService;
use App\Services\RunCloud\RunCloudSslService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ConfigureRunCloudSubdomainSsl implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $deleteWhenMissingModels = true;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public Deployment $deployment,
        public string $subdomain,
        public ?int $domainId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->deployment->getKey().':'.$this->deployment->runcloud_web_app_id.':'.$this->subdomain;
    }

    public function handle(
        CloudflareClient $cloudflareClient,
        CloudflareDnsService $cloudflareDnsService,
        RunCloudSslService $runCloudSslService,
        ApiUsageContext $apiUsageContext,
    ): void {
        $context = [
            'deployment_id' => $this->deployment->getKey(),
            'domain' => $this->subdomain,
            'job' => self::class,
            'job_attempt' => $this->attempts(),
        ];
        Log::shareContext($context);

        try {
            $server = $this->deployment->server;
            $serverId = (int) ($server?->server_id ?? 0);
            $appId = (int) $this->deployment->runcloud_web_app_id;
            if (! $serverId || ! $appId || blank($server?->ip_address)) {
                throw new RuntimeException('The deployment server or RunCloud web application is not configured.');
            }

            $apiUsageContext->forDeployment((int) $this->deployment->getKey(), function () use ($cloudflareClient, $cloudflareDnsService, $runCloudSslService, $server, $serverId, $appId): void {
                $zoneId = $cloudflareClient->getZoneId($this->deployment->domain);
                $cloudflareDnsService->upsertARecord($zoneId, $this->subdomain, $server->ip_address, true);
                $runCloudSslService->configureAdvancedSsl($serverId, $appId, $this->subdomain, $this->domainId);
            });

            $this->deployment->logs()->create([
                'step_name' => 'configuring_subdomain_ssl',
                'status' => 'success',
                'message' => "{$this->subdomain}: Cloudflare DNS configured with proxy enabled; Let's Encrypt SSL configured with HTTPS redirection.",
            ]);
        } finally {
            Log::withoutContext(array_keys($context));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->deployment->logs()->create([
            'step_name' => 'configuring_subdomain_ssl',
            'status' => 'error',
            'message' => "{$this->subdomain}: Cloudflare DNS and SSL configuration failed: ".($exception?->getMessage() ?? 'Unknown error.'),
        ]);
    }
}
