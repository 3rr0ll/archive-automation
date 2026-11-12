<?php

namespace Tests\Feature;

use App\Jobs\ConfigureRunCloudSubdomainSsl;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ConfigureRunCloudSubdomainSslTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.runcloud.base_url', 'https://manage.runcloud.io/api/v3');
        Config::set('services.runcloud.api_token', 'runcloud-test-token');
        Config::set('services.cloudflare.base_url', 'https://api.cloudflare.com/client/v4');
        Config::set('services.cloudflare.api_token', 'cloudflare-test-token');
    }

    private function createDeployment(): Deployment
    {
        Server::factory()->create(['server_id' => 10, 'ip_address' => '192.0.2.10']);

        return Deployment::create([
            'user_id' => User::factory()->create()->id,
            'server_id' => 10,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_status' => 'ready',
            'status' => 'completed',
            'current_step' => 'completed',
            'runcloud_web_app_id' => 77,
        ]);
    }

    public function test_it_creates_proxied_dns_before_requesting_domain_ssl(): void
    {
        $deployment = $this->createDeployment();
        $dnsCreated = false;
        Http::preventStrayRequests();
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?*' => Http::response(['result' => [['id' => 'test-zone']]]),
            'https://api.cloudflare.com/client/v4/zones/test-zone/dns_records*' => function (Request $request) use (&$dnsCreated) {
                if ($request->method() === 'GET') {
                    return Http::response(['result' => []]);
                }

                $this->assertSame('POST', $request->method());
                $this->assertSame('A', $request['type']);
                $this->assertSame('blog.example.com', $request['name']);
                $this->assertSame('192.0.2.10', $request['content']);
                $this->assertTrue($request['proxied']);
                $dnsCreated = true;

                return Http::response(['result' => ['id' => 'dns-record']], 201);
            },
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/ssl/advanced' => Http::response(['advancedSSL' => true, 'autoSSL' => true]),
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains/999/ssl' => function (Request $request) use (&$dnsCreated) {
                $this->assertTrue($dnsCreated, 'DNS must exist before RunCloud verifies it for SSL.');
                $this->assertSame('dns-01', $request['authorizationMethod']);
                $this->assertSame('letsencrypt', $request['provider']);
                $this->assertFalse($request['enableHttp']);

                return Http::response(['id' => 555, 'method' => 'letsencrypt']);
            },
        ]);

        $this->app->call([new ConfigureRunCloudSubdomainSsl($deployment, 'blog.example.com', 999), 'handle']);

        Http::assertSentCount(5);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'configuring_subdomain_ssl',
            'status' => 'success',
            'message' => "blog.example.com: Cloudflare DNS configured with proxy enabled; Let's Encrypt SSL configured with HTTPS redirection.",
        ]);
        $this->assertSame('completed', $deployment->fresh()->status->value);
    }

    public function test_it_does_not_request_ssl_if_dns_creation_returns_403(): void
    {
        $deployment = $this->createDeployment();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?*' => Http::response(['result' => [['id' => 'test-zone']]]),
            'https://api.cloudflare.com/client/v4/zones/test-zone/dns_records*' => fn (Request $request) => $request->method() === 'GET'
                ? Http::response(['result' => []])
                : Http::response(['errors' => [['message' => 'DNS edit permission denied.']]], 403),
        ]);

        try {
            $this->app->call([new ConfigureRunCloudSubdomainSsl($deployment, 'blog.example.com', 999), 'handle']);
            $this->fail('DNS failure must stop certificate deployment.');
        } catch (\Exception $exception) {
            $this->assertSame('Cloudflare API error (403): DNS edit permission denied.', $exception->getMessage());
        }

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'manage.runcloud.io'));
        $this->assertDatabaseMissing('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'configuring_subdomain_ssl',
            'status' => 'success',
        ]);
    }

    public function test_terminal_failure_records_the_subdomain_and_actual_error(): void
    {
        $deployment = $this->createDeployment();
        $job = new ConfigureRunCloudSubdomainSsl($deployment, 'blog.example.com', 999);

        $job->failed(new RuntimeException('Domain is not found in the selected DNS provider.'));

        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'configuring_subdomain_ssl',
            'status' => 'error',
            'message' => 'blog.example.com: Cloudflare DNS and SSL configuration failed: Domain is not found in the selected DNS provider.',
        ]);
    }
}
