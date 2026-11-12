<?php

namespace Tests\Unit;

use App\Services\RunCloud\RunCloudWebAppService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunCloudSubdomainAliasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.runcloud.base_url', 'https://manage.runcloud.io/api/v3');
        Config::set('services.runcloud.api_key', 'test-key');
        Config::set('services.runcloud.api_secret', 'test-secret');
        Config::set('services.runcloud.cloudflare_external_api_id', 42310);
    }

    public function test_it_attaches_subdomain_aliases_with_cloudflare_and_proxy_settings(): void
    {
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/50/domains' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([
                        'data' => [
                            ['id' => 1, 'name' => 'example.com', 'type' => 'primary'],
                        ],
                    ], 200);
                }

                if ($request->method() === 'POST') {
                    $this->assertSame('blog.example.com', $request['name']);
                    $this->assertSame('alias', $request['type']);
                    $this->assertFalse($request['www']);
                    $this->assertTrue($request['allowDnsIntegration']);
                    $this->assertTrue($request['setAsProxied']);
                    $this->assertSame('cloudflare', $request['dnsProvider']);
                    $this->assertSame(42310, $request['cfApiKeyId']);
                    $this->assertTrue($request['updateCloudflare']);
                    $this->assertSame('none', $request['redirection']);

                    return Http::response([
                        'data' => [
                            'id' => 101,
                            'name' => 'blog.example.com',
                            'type' => 'alias',
                        ],
                    ], 201);
                }

                return Http::response([], 404);
            },
        ]);

        $service = app(RunCloudWebAppService::class);

        $result = $service->attachSubdomainAliases(10, 50, 'example.com', ['blog-sub']);

        $this->assertCount(1, $result['added']);
        $this->assertSame('blog.example.com', $result['added'][0]['subdomain']);
        $this->assertSame('blog', $result['added'][0]['name']);
        $this->assertSame(101, $result['added'][0]['id']);
        $this->assertEmpty($result['skipped']);
        $this->assertEmpty($result['failed']);
    }

    public function test_it_skips_subdomains_that_already_exist_in_runcloud(): void
    {
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/50/domains' => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'example.com', 'type' => 'primary'],
                    ['id' => 2, 'name' => 'shop.example.com', 'type' => 'alias'],
                ],
            ], 200),
        ]);

        $service = app(RunCloudWebAppService::class);

        $result = $service->attachSubdomainAliases(10, 50, 'example.com', ['shop-sub']);

        $this->assertEmpty($result['added']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('shop.example.com', $result['skipped'][0]['subdomain']);
        $this->assertSame('shop', $result['skipped'][0]['name']);
        $this->assertSame(2, $result['skipped'][0]['id']);
        $this->assertStringContainsString('already attached', $result['skipped'][0]['reason']);
        $this->assertEmpty($result['failed']);

        Http::assertSentCount(1); // Only GET /domains was called, no POST
    }

    public function test_it_validates_name_and_processes_each_subdomain_independently(): void
    {
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/50/domains' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([
                        'data' => [
                            ['id' => 1, 'name' => 'example.com', 'type' => 'primary'],
                            ['id' => 2, 'name' => 'existing.example.com', 'type' => 'alias'],
                        ],
                    ], 200);
                }

                if ($request->method() === 'POST') {
                    if ($request['name'] === 'good.example.com') {
                        return Http::response(['data' => ['id' => 201, 'name' => 'good.example.com']], 201);
                    }

                    if ($request['name'] === 'fail.example.com') {
                        return Http::response(['message' => 'Rate limit exceeded on provider.'], 422);
                    }
                }

                return Http::response([], 400);
            },
        ]);

        $service = app(RunCloudWebAppService::class);

        $result = $service->attachSubdomainAliases(10, 50, 'example.com', [
            'good-sub',
            'INVALID NAME!@#',
            'existing-sub',
            'fail-sub',
        ]);

        // 1 added
        $this->assertCount(1, $result['added']);
        $this->assertSame('good.example.com', $result['added'][0]['subdomain']);

        // 1 skipped (existing)
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('existing.example.com', $result['skipped'][0]['subdomain']);

        // 2 failed (one invalid name, one API error)
        $this->assertCount(2, $result['failed']);
        $this->assertStringContainsString('Invalid subdomain name', $result['failed'][0]['error']);
        $this->assertSame('fail.example.com', $result['failed'][1]['subdomain']);
        $this->assertStringContainsString('Rate limit exceeded', $result['failed'][1]['error']);
    }
}
