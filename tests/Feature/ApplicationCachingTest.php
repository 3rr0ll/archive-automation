<?php

namespace Tests\Feature;

use App\Enums\ArchiveStatus;
use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Models\EnvironmentVariable;
use App\Models\Server;
use App\Models\User;
use App\Services\Cache\ApplicationCache;
use App\Services\Cloudflare\CloudflareClient;
use App\Services\Cloudflare\CloudflareDnsService;
use App\Services\Cloudflare\CloudflareWorkerService;
use App\Services\Deployment\DeploymentStatisticsService;
use App\Services\InternetArchive\InternetArchiveSettings;
use App\Services\RunCloud\RunCloudService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApplicationCachingTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.cloudflare.api_token' => 'test-cloudflare-token',
            'services.runcloud.api_token' => 'test-runcloud-token',
        ]);
    }

    public function test_cloudflare_zone_resolution_is_cached_and_provider_invalidation_refreshes_it(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones*' => Http::response([
                'result' => [['id' => 'zone-123']],
            ]),
        ]);

        $client = app(CloudflareClient::class);

        $this->assertSame('zone-123', $client->getZoneId('www.example.com'));
        $this->assertSame('zone-123', $client->getZoneId('example.com'));
        $this->assertSame(1, $this->recordedRequestsTo('/zones'));

        app(ApplicationCache::class)->invalidateCloudflare();

        $this->assertSame('zone-123', $client->getZoneId('example.com'));
        $this->assertSame(2, $this->recordedRequestsTo('/zones'));
    }

    public function test_cloudflare_dns_and_worker_mutations_invalidate_their_read_caches(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

            if (str_ends_with($path, '/zones')) {
                return Http::response(['result' => [['id' => 'zone-123']]]);
            }

            if (str_contains($request->url(), '/dns_records')) {
                return $request->method() === 'GET'
                    ? Http::response(['result' => [], 'result_info' => ['total_pages' => 1]])
                    : Http::response(['result' => ['id' => 'dns-1']]);
            }

            if (str_contains($request->url(), '/workers/routes')) {
                return $request->method() === 'GET'
                    ? Http::response(['result' => []])
                    : Http::response(['result' => ['id' => 'route-1']]);
            }

            return Http::response([], 404);
        });

        $dns = app(CloudflareDnsService::class);
        $workers = app(CloudflareWorkerService::class);

        $dns->getDnsRecords('example.com');
        $dns->getDnsRecords('example.com');
        $dns->addARecord('zone-123', '@', '192.0.2.10');
        $dns->getDnsRecords('example.com');

        $workers->getWorkerRoutes('zone-123');
        $workers->getWorkerRoutes('zone-123');
        $workers->createWorkerRoute('zone-123', 'example.com/*', 'worker');
        $workers->getWorkerRoutes('zone-123');

        $this->assertSame(2, $this->recordedRequestsTo('/dns_records', 'GET'));
        $this->assertSame(2, $this->recordedRequestsTo('/workers/routes', 'GET'));
    }

    public function test_runcloud_server_reads_are_cached_and_invalidated_by_server_changes(): void
    {
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers' => Http::response([
                'data' => [['id' => 99, 'name' => 'Production']],
            ]),
        ]);

        $runCloud = app(RunCloudService::class);

        $runCloud->getServers();
        $runCloud->getServers();
        $this->assertSame(1, $this->recordedRequestsTo('/servers'));

        Server::create([
            'server_id' => 99,
            'name' => 'Production',
            'ip_address' => '192.0.2.10',
            'is_active' => true,
        ]);

        $runCloud->getServers();
        $this->assertSame(2, $this->recordedRequestsTo('/servers'));
    }

    public function test_archive_settings_and_deployment_statistics_are_invalidated_after_changes(): void
    {
        $settings = app(InternetArchiveSettings::class);
        $defaults = $settings->all();

        EnvironmentVariable::create([
            'key' => 'INTERNET_ARCHIVE_SETTINGS',
            'value' => json_encode([
                ...$defaults,
                'timeout' => 45,
            ], JSON_THROW_ON_ERROR),
            'type' => 'config',
            'environments' => 'Production',
        ]);

        $this->assertSame(45, $settings->all()['timeout']);

        $statistics = app(DeploymentStatisticsService::class);
        $this->assertSame(0, $statistics->calculate(Deployment::query())['total']);

        $user = User::factory()->create();
        $server = Server::create([
            'server_id' => 123,
            'name' => 'Archive Server',
            'ip_address' => '192.0.2.20',
            'is_active' => true,
        ]);
        Deployment::create([
            'user_id' => $user->id,
            'server_id' => $server->server_id,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_source' => 'url',
            'archive_url' => 'https://example.test/archive.zip',
            'archive_status' => ArchiveStatus::Waiting,
            'status' => DeploymentStatus::Pending,
            'current_step' => 'waiting_for_archive',
        ]);

        $this->assertSame(1, $statistics->calculate(Deployment::query())['total']);
    }

    private function recordedRequestsTo(string $path, ?string $method = null): int
    {
        return Http::recorded(function (Request $request) use ($path, $method): bool {
            return str_contains(parse_url($request->url(), PHP_URL_PATH) ?: '', $path)
                && ($method === null || $request->method() === $method);
        })->count();
    }
}
