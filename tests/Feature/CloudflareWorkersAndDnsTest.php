<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Cloudflare\CloudflareService;
use App\Services\RunCloud\RunCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareWorkersAndDnsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'cloudflare-admin@example.com',
            'role' => 'admin',
        ]);

        Config::set('services.cloudflare.api_token', 'test-cloudflare-token');
        Config::set('services.cloudflare.base_url', 'https://api.cloudflare.com/client/v4');
        Config::set('services.runcloud.api_key', 'test-runcloud-key');
        Config::set('services.runcloud.base_url', 'https://manage.runcloud.io/api/v3');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/cloudflare/workers', [
            'domain' => 'example.com',
            'version' => 'www',
        ])->assertStatus(401);

        $this->postJson('/api/cloudflare/dns/setup-runcloud', [
            'domain' => 'example.com',
            'server_ip' => '1.2.3.4',
        ])->assertStatus(401);
    }

    public function test_change_workers_for_www_version(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?name=example.com' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'zone-abc-123', 'name' => 'example.com'],
                ],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes' => Http::response([
                'success' => true,
                'result' => [],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes*' => function ($request) {
                if ($request->method() === 'POST') {
                    $data = $request->data();
                    return Http::response([
                        'success' => true,
                        'result' => [
                            'id' => 'route-' . md5($data['pattern']),
                            'pattern' => $data['pattern'],
                            'script' => $data['script'],
                        ],
                    ], 200);
                }
                return Http::response(['success' => false], 404);
            },

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache' => Http::response([
                'success' => true,
                'result' => ['id' => 'zone-abc-123'],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cloudflare/workers', [
                'domain' => 'example.com',
                'version' => 'www',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.domain', 'example.com')
            ->assertJsonPath('data.version', 'www')
            ->assertJsonPath('data.cache_purged', true)
            ->assertJsonPath('data.routes.0.pattern', 'www.example.com/*')
            ->assertJsonPath('data.routes.0.script', CloudflareService::WORKER_WWW_MAIN)
            ->assertJsonPath('data.routes.0.action', 'created')
            ->assertJsonPath('data.routes.1.pattern', 'example.com/*')
            ->assertJsonPath('data.routes.1.script', CloudflareService::WORKER_WWW_REDIRECT)
            ->assertJsonPath('data.routes.1.action', 'created');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes'
                && $request->method() === 'POST'
                && $request['pattern'] === 'www.example.com/*'
                && $request['script'] === 'all-scripts-worker-www';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes'
                && $request->method() === 'POST'
                && $request['pattern'] === 'example.com/*'
                && $request['script'] === 'domain-redirect-root-to-www';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache'
                && $request->method() === 'POST'
                && $request['purge_everything'] === true;
        });
    }

    public function test_change_workers_for_nonwww_version(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?name=example.com' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'zone-abc-123', 'name' => 'example.com'],
                ],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes' => Http::response([
                'success' => true,
                'result' => [],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes*' => function ($request) {
                if ($request->method() === 'POST') {
                    $data = $request->data();
                    return Http::response([
                        'success' => true,
                        'result' => [
                            'id' => 'route-' . md5($data['pattern']),
                            'pattern' => $data['pattern'],
                            'script' => $data['script'],
                        ],
                    ], 200);
                }
                return Http::response(['success' => false], 404);
            },

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache' => Http::response([
                'success' => true,
                'result' => ['id' => 'zone-abc-123'],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cloudflare/workers', [
                'domain' => 'example.com',
                'version' => 'nonwww',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.domain', 'example.com')
            ->assertJsonPath('data.version', 'nonwww')
            ->assertJsonPath('data.cache_purged', true)
            ->assertJsonPath('data.routes.0.pattern', 'example.com/*')
            ->assertJsonPath('data.routes.0.script', CloudflareService::WORKER_ROOT_MAIN)
            ->assertJsonPath('data.routes.1.pattern', 'www.example.com/*')
            ->assertJsonPath('data.routes.1.script', CloudflareService::WORKER_ROOT_REDIRECT);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes'
                && $request->method() === 'POST'
                && $request['pattern'] === 'example.com/*'
                && $request['script'] === 'all-scripts-worker-root';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes'
                && $request->method() === 'POST'
                && $request['pattern'] === 'www.example.com/*'
                && $request['script'] === 'domain-redirect-www-to-root';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache'
                && $request->method() === 'POST'
                && $request['purge_everything'] === true;
        });
    }

    public function test_change_workers_updates_existing_route(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?name=example.com' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'zone-abc-123', 'name' => 'example.com'],
                ],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'existing-route-root',
                        'pattern' => 'example.com/*',
                        'script' => 'old-worker-script',
                    ],
                    [
                        'id' => 'existing-route-www',
                        'pattern' => 'www.example.com/*',
                        'script' => 'old-worker-script',
                    ],
                ],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes/*' => function ($request) {
                if ($request->method() === 'PUT') {
                    $data = $request->data();
                    return Http::response([
                        'success' => true,
                        'result' => [
                            'id' => 'updated-route',
                            'pattern' => $data['pattern'],
                            'script' => $data['script'],
                        ],
                    ], 200);
                }
                return Http::response(['success' => false], 404);
            },

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache' => Http::response([
                'success' => true,
                'result' => ['id' => 'zone-abc-123'],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cloudflare/workers', [
                'domain' => 'example.com',
                'version' => 'nonwww',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.cache_purged', true)
            ->assertJsonPath('data.routes.0.action', 'updated')
            ->assertJsonPath('data.routes.1.action', 'updated');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache'
                && $request->method() === 'POST';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes/existing-route-root'
                && $request->method() === 'PUT'
                && $request['script'] === 'all-scripts-worker-root';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes/existing-route-www'
                && $request->method() === 'PUT'
                && $request['script'] === 'domain-redirect-www-to-root';
        });
    }

    public function test_change_workers_uses_environment_variable_model(): void
    {
        \App\Models\EnvironmentVariable::create([
            'key' => 'CLOUDFLARE_WORKER_WWW_MAIN',
            'value' => 'custom-worker-www-main',
            'type' => 'config',
            'environments' => 'Production',
            'user_id' => $this->user->id,
        ]);

        \App\Models\EnvironmentVariable::create([
            'key' => 'CLOUDFLARE_WORKER_WWW_REDIRECT',
            'value' => 'custom-worker-www-redirect',
            'type' => 'config',
            'environments' => 'Production',
            'user_id' => $this->user->id,
        ]);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?name=example.com' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'zone-abc-123', 'name' => 'example.com'],
                ],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes' => Http::response([
                'success' => true,
                'result' => [],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes*' => function ($request) {
                if ($request->method() === 'POST') {
                    $data = $request->data();
                    return Http::response([
                        'success' => true,
                        'result' => [
                            'id' => 'route-' . md5($data['pattern']),
                            'pattern' => $data['pattern'],
                            'script' => $data['script'],
                        ],
                    ], 200);
                }
                return Http::response(['success' => false], 404);
            },

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache' => Http::response([
                'success' => true,
                'result' => ['id' => 'zone-abc-123'],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cloudflare/workers', [
                'domain' => 'example.com',
                'version' => 'www',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.routes.0.script', 'custom-worker-www-main')
            ->assertJsonPath('data.routes.1.script', 'custom-worker-www-redirect');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes'
                && $request->method() === 'POST'
                && $request['pattern'] === 'www.example.com/*'
                && $request['script'] === 'custom-worker-www-main';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/workers/routes'
                && $request->method() === 'POST'
                && $request['pattern'] === 'example.com/*'
                && $request['script'] === 'custom-worker-www-redirect';
        });
    }

    public function test_setup_runcloud_dns_deletes_all_records_and_adds_root_and_www_a_records(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?name=example.com' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'zone-abc-123', 'name' => 'example.com'],
                ],
            ], 200),

            // Existing DNS records: one old A record and one apex NS record
            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/dns_records?page=1&per_page=100' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'rec-1', 'type' => 'A', 'name' => 'mail.example.com', 'content' => '1.1.1.1'],
                    ['id' => 'rec-ns', 'type' => 'NS', 'name' => 'example.com', 'content' => 'ns1.cloudflare.com'],
                ],
                'result_info' => ['total_pages' => 1],
            ], 200),

            // Delete record rec-1
            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/dns_records/rec-1' => Http::response([
                'success' => true,
                'result' => ['id' => 'rec-1'],
            ], 200),

            // Create A record
            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/dns_records' => function ($request) {
                if ($request->method() === 'POST') {
                    $data = $request->data();
                    return Http::response([
                        'success' => true,
                        'result' => [
                            'id' => 'rec-new-' . $data['name'],
                            'type' => $data['type'],
                            'name' => $data['name'],
                            'content' => $data['content'],
                            'proxied' => $data['proxied'],
                        ],
                    ], 200);
                }
                return Http::response(['success' => false], 404);
            },
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cloudflare/dns/setup-runcloud', [
                'domain' => 'example.com',
                'server_ip' => '198.51.100.42',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.server_ip', '198.51.100.42')
            ->assertJsonPath('data.deleted_records_count', 1)
            ->assertJsonPath('data.records.root.name', '@')
            ->assertJsonPath('data.records.root.content', '198.51.100.42')
            ->assertJsonPath('data.records.root.proxied', true)
            ->assertJsonPath('data.records.www.name', 'www')
            ->assertJsonPath('data.records.www.content', '198.51.100.42')
            ->assertJsonPath('data.records.www.proxied', true);

        // Verify rec-1 was deleted
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/dns_records/rec-1'
                && $request->method() === 'DELETE';
        });

        // Verify @ A record was created with proxied=true
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/dns_records'
                && $request->method() === 'POST'
                && $request['name'] === '@'
                && $request['type'] === 'A'
                && $request['content'] === '198.51.100.42'
                && $request['proxied'] === true;
        });

        // Verify www A record was created with proxied=true
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/dns_records'
                && $request->method() === 'POST'
                && $request['name'] === 'www'
                && $request['type'] === 'A'
                && $request['content'] === '198.51.100.42'
                && $request['proxied'] === true;
        });
    }

    public function test_runcloud_service_removes_dns_integration_and_triggers_direct_cloudflare_dns(): void
    {
        Config::set('services.runcloud.server_id', 99);
        Config::set('services.runcloud.server_ip', '198.51.100.55');

        Http::fake([
            // RunCloud Server
            'https://manage.runcloud.io/api/v3/servers/99' => Http::response([
                'data' => [
                    'id' => 99,
                    'name' => 'Prod Server',
                    'ipAddress' => '198.51.100.55',
                ],
            ], 200),

            // RunCloud WebApps list (duplicate check)
            'https://manage.runcloud.io/api/v3/servers/99/webapps' => Http::response([
                'data' => [],
            ], 200),

            // RunCloud system users
            'https://manage.runcloud.io/api/v3/servers/99/users' => Http::response([
                'data' => [
                    ['id' => 1645, 'username' => 'runcloud'],
                ],
            ], 200),

            // RunCloud create custom webapp
            'https://manage.runcloud.io/api/v3/servers/99/webapps/custom' => Http::response([
                'data' => [
                    'id' => 501,
                    'name' => 'mysite',
                ],
            ], 200),

            // RunCloud attach domain
            'https://manage.runcloud.io/api/v3/servers/99/webapps/501/domains' => function ($request) {
                // Ensure RunCloud payload does NOT contain dnsIntegration, allowDnsIntegration, or proxied
                if (isset($request['dnsIntegration']) || isset($request['allowDnsIntegration']) || isset($request['proxied'])) {
                    return Http::response(['message' => 'RunCloud DNS integration should be removed.'], 400);
                }

                return Http::response([
                    'data' => [
                        'id' => 777,
                        'name' => 'mysite.com',
                        'www' => true,
                    ],
                ], 200);
            },

            // RunCloud SSL
            'https://manage.runcloud.io/api/v3/servers/99/webapps/501/ssl' => Http::response([
                'data' => ['success' => true],
            ], 200),

            // Cloudflare API mock for direct DNS setup
            'https://api.cloudflare.com/client/v4/zones?name=mysite.com' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'zone-mysite', 'name' => 'mysite.com'],
                ],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-mysite/dns_records?page=1&per_page=100' => Http::response([
                'success' => true,
                'result' => [],
                'result_info' => ['total_pages' => 1],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-mysite/dns_records' => Http::response([
                'success' => true,
                'result' => ['id' => 'new-rec'],
            ], 200),
        ]);

        $runCloudService = app(RunCloudService::class);
        $result = $runCloudService->createEmptyWebApplication('mysite.com', 99);

        $this->assertTrue($result['success']);
        $this->assertEquals('cloudflare_direct', $result['dns_integration']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://manage.runcloud.io/api/v3/servers/99/webapps/custom'
                && $request['user'] === 1645
                && $request['phpVersion'] === 'php81rc'
                && $request['stack'] === 'hybrid';
        });

        // Assert domain payload sent to RunCloud does NOT contain dnsIntegration
        Http::assertSent(function ($request) {
            if ($request->url() === 'https://manage.runcloud.io/api/v3/servers/99/webapps/501/domains') {
                return ! isset($request['dnsIntegration'])
                    && ! isset($request['allowDnsIntegration'])
                    && ! isset($request['proxied'])
                    && $request['name'] === 'mysite.com'
                    && $request['www'] === true
                    && $request['redirection'] === 'none';
            }
            return false;
        });

        // Assert Cloudflare DNS was called directly for @ and www with server IP
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-mysite/dns_records'
                && $request['name'] === '@'
                && $request['content'] === '198.51.100.55';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-mysite/dns_records'
                && $request['name'] === 'www'
                && $request['content'] === '198.51.100.55';
        });
    }

    public function test_purge_cache_endpoint(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?name=example.com' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'zone-abc-123', 'name' => 'example.com'],
                ],
            ], 200),

            'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache' => Http::response([
                'success' => true,
                'result' => ['id' => 'zone-abc-123'],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cloudflare/cache/purge', [
                'domain' => 'example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cloudflare cache purged successfully.');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache'
                && $request->method() === 'POST'
                && $request['purge_everything'] === true;
        });
    }
}
