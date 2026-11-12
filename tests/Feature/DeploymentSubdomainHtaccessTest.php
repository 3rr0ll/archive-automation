<?php

namespace Tests\Feature;

use App\Jobs\ConfigureRunCloudSubdomainSsl;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use App\Services\Sftp\SftpFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class DeploymentSubdomainHtaccessTest extends TestCase
{
    use RefreshDatabase;

    private function createDeployment(array $attributes = []): Deployment
    {
        return Deployment::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'server_id' => 10,
            'domain' => 'example.com',
            'version' => 'non-www',
            'archive_status' => 'waiting',
            'status' => 'pending',
            'current_step' => 'queued',
            'runcloud_web_app_name' => 'exampleapp',
        ], $attributes));
    }

    public function test_it_returns_htaccess_status_and_configured_subdomains(): void
    {
        $user = User::factory()->create();
        Server::factory()->create([
            'server_id' => 10,
            'ip_address' => '192.0.2.10',
        ]);
        $deployment = $this->createDeployment([
            'user_id' => $user->id,
            'server_id' => 10,
        ]);

        $sftp = Mockery::mock(SftpFileService::class);
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.10', '/home/runcloud/webapps/exampleapp/public_html/.htaccess')
            ->once()
            ->andReturnTrue();
        $sftp->shouldReceive('readFile')
            ->with('192.0.2.10', '/home/runcloud/webapps/exampleapp/public_html/.htaccess')
            ->once()
            ->andReturn("<IfModule mod_rewrite.c>\n</IfModule>\n# BEGIN Subdomain Routing\n# Route subdomain to folder: blog\n# END Subdomain Routing");

        $this->app->instance(SftpFileService::class, $sftp);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/deployments/{$deployment->id}/subdomain-htaccess");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'exists' => true,
                    'has_if_module' => true,
                    'configured_subdomains' => ['blog'],
                ],
            ]);
    }

    public function test_it_generates_a_preview_of_rules_and_diff(): void
    {
        $user = User::factory()->create();
        Server::factory()->create([
            'server_id' => 10,
            'ip_address' => '192.0.2.10',
        ]);
        $deployment = $this->createDeployment([
            'user_id' => $user->id,
            'server_id' => 10,
        ]);

        $sftp = Mockery::mock(SftpFileService::class);
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.10', '/home/runcloud/webapps/exampleapp/public_html/.htaccess')
            ->once()
            ->andReturnTrue();
        $sftp->shouldReceive('readFile')
            ->with('192.0.2.10', '/home/runcloud/webapps/exampleapp/public_html/.htaccess')
            ->once()
            ->andReturn("<IfModule mod_rewrite.c>\n</IfModule>\n# BEGIN Subdomain Routing\n# Route subdomain to folder: old\n# END Subdomain Routing");

        $this->app->instance(SftpFileService::class, $sftp);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/deployments/{$deployment->id}/subdomain-htaccess/preview", [
                'folders' => ['blog', 'shop'],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'added' => ['blog', 'shop'],
                    'removed' => ['old'],
                    'has_if_module' => true,
                    'notice' => null,
                ],
            ]);

        $rules = $response->json('data.rules');
        $this->assertStringContainsString('# Route subdomain to folder: blog', $rules);
        $this->assertStringContainsString('RewriteCond %{HTTP_HOST} ^blog\.example\.com$ [NC]', $rules);
        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/blog-sub(/|$)', $rules);
        $this->assertStringContainsString('RewriteRule ^(.*)$ /blog-sub/$1 [END]', $rules);
        $this->assertStringContainsString('# Route subdomain to folder: shop', $rules);
    }

    public function test_it_applies_subdomain_rules_creates_backup_and_records_deployment_log(): void
    {
        $user = User::factory()->create();
        Server::factory()->create([
            'server_id' => 10,
            'ip_address' => '192.0.2.10',
        ]);
        $deployment = $this->createDeployment([
            'user_id' => $user->id,
            'server_id' => 10,
        ]);

        $existingContent = "<IfModule mod_rewrite.c>\nRewriteEngine On\n</IfModule>\n";

        $sftp = Mockery::mock(SftpFileService::class);
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.10', '/home/runcloud/webapps/exampleapp/public_html/.htaccess')
            ->andReturnTrue();
        $sftp->shouldReceive('readFile')
            ->with('192.0.2.10', '/home/runcloud/webapps/exampleapp/public_html/.htaccess')
            ->once()
            ->andReturn($existingContent);
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.10', Mockery::pattern('#/\.htaccess\.bak-\d{8}-\d{6}#'))
            ->once()
            ->andReturnFalse();
        $sftp->shouldReceive('getFilePermissions')
            ->with('192.0.2.10', '/home/runcloud/webapps/exampleapp/public_html/.htaccess')
            ->once()
            ->andReturn(0644);

        // Backup
        $sftp->shouldReceive('writeFile')
            ->once()
            ->with(
                '192.0.2.10',
                Mockery::pattern('#/\.htaccess\.bak-\d{8}-\d{6}#'),
                $existingContent,
                0644,
            );

        // Updated file
        $sftp->shouldReceive('writeFile')
            ->once()
            ->with(
                '192.0.2.10',
                '/home/runcloud/webapps/exampleapp/public_html/.htaccess',
                Mockery::on(function (string $content): bool {
                    return str_contains($content, '# BEGIN Subdomain Routing')
                        && str_contains($content, '# Route subdomain to folder: blog')
                        && str_contains($content, 'RewriteCond %{HTTP_HOST} ^blog\.example\.com$ [NC]')
                        && str_contains($content, 'RewriteRule ^(.*)$ /blog-sub/$1 [END]');
                }),
                0644,
            );

        $this->app->instance(SftpFileService::class, $sftp);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/deployments/{$deployment->id}/subdomain-htaccess/apply", [
                'folders' => ['blog-sub'],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'added' => ['blog'],
                    'configured_subdomains' => ['blog'],
                ],
            ]);

        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'configuring_subdomain_htaccess',
            'status' => 'success',
        ]);
    }

    public function test_it_removes_unckecked_subdomain_block_when_applied(): void
    {
        $user = User::factory()->create();
        Server::factory()->create([
            'server_id' => 10,
            'ip_address' => '192.0.2.10',
        ]);
        $deployment = $this->createDeployment([
            'user_id' => $user->id,
            'server_id' => 10,
        ]);

        $existingContent = implode("\n", [
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            '</IfModule>',
            '',
            '# BEGIN Subdomain Routing',
            '# Route subdomain to folder: blog',
            'RewriteCond %{HTTP_HOST} ^blog\.example\.com$ [NC]',
            'RewriteCond %{REQUEST_URI} !^/blog-sub(/|$)',
            'RewriteRule ^(.*)$ /blog-sub/$1 [END]',
            '',
            '# Route subdomain to folder: shop',
            'RewriteCond %{HTTP_HOST} ^shop\.example\.com$ [NC]',
            'RewriteCond %{REQUEST_URI} !^/shop-sub(/|$)',
            'RewriteRule ^(.*)$ /shop-sub/$1 [END]',
            '# END Subdomain Routing',
        ]);

        $sftp = Mockery::mock(SftpFileService::class);
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.10', '/home/runcloud/webapps/exampleapp/public_html/.htaccess')
            ->andReturnTrue();
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.10', Mockery::pattern('#/\.htaccess\.bak-\d{8}-\d{6}#'))
            ->once()
            ->andReturnFalse();
        $sftp->shouldReceive('readFile')->once()->andReturn($existingContent);
        $sftp->shouldReceive('getFilePermissions')->once()->andReturn(0644);
        $sftp->shouldReceive('writeFile')
            ->once()
            ->with(
                '192.0.2.10',
                Mockery::pattern('#/\.htaccess\.bak-\d{8}-\d{6}#'),
                $existingContent,
                0644,
            );
        $sftp->shouldReceive('writeFile')
            ->once()
            ->with(
                '192.0.2.10',
                '/home/runcloud/webapps/exampleapp/public_html/.htaccess',
                Mockery::on(function (string $content): bool {
                    return ! str_contains($content, 'folder: blog')
                        && str_contains($content, 'folder: shop');
                }),
                0644,
            );

        $this->app->instance(SftpFileService::class, $sftp);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/deployments/{$deployment->id}/subdomain-htaccess/apply", [
                'folders' => ['shop'],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'added' => [],
                    'updated' => ['shop'],
                    'removed' => ['blog'],
                    'configured_subdomains' => ['shop'],
                ],
            ]);
    }

    public function test_it_automatically_attaches_subdomain_alias_in_runcloud_after_htaccess_is_applied(): void
    {
        $user = User::factory()->create();
        Server::factory()->create([
            'server_id' => 10,
            'ip_address' => '192.0.2.10',
        ]);
        $deployment = $this->createDeployment([
            'user_id' => $user->id,
            'server_id' => 10,
            'runcloud_web_app_id' => 77,
            'domain' => 'example.com',
        ]);

        $existingHtaccess = "<IfModule mod_rewrite.c>\n</IfModule>\n";

        $sftp = Mockery::mock(SftpFileService::class);
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.10', '/home/runcloud/webapps/exampleapp/public_html/.htaccess')
            ->andReturnTrue();
        $sftp->shouldReceive('fileExists')
            ->with('192.0.2.10', Mockery::pattern('#/\.htaccess\.bak-\d{8}-\d{6}#'))
            ->once()
            ->andReturnFalse();
        $sftp->shouldReceive('readFile')->once()->andReturn($existingHtaccess);
        $sftp->shouldReceive('getFilePermissions')->once()->andReturn(0644);
        $sftp->shouldReceive('writeFile')->twice();

        $this->app->instance(SftpFileService::class, $sftp);

        // RunCloud HTTP fake
        Config::set('services.runcloud.base_url', 'https://manage.runcloud.io/api/v3');
        Config::set('services.runcloud.api_key', 'test-key');
        Config::set('services.runcloud.api_secret', 'test-secret');

        Queue::fake([ConfigureRunCloudSubdomainSsl::class]);
        Http::preventStrayRequests();

        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains' => function (Request $request) {
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

                    return Http::response([
                        'data' => ['id' => 999, 'name' => 'blog.example.com'],
                    ], 201);
                }

                return Http::response([], 400);
            },
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/deployments/{$deployment->id}/subdomain-htaccess/apply", [
                'folders' => ['blog-sub'],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'runcloud' => [
                        'added' => [
                            ['subdomain' => 'blog.example.com', 'name' => 'blog', 'ssl' => 'queued'],
                        ],
                        'skipped' => [],
                        'failed' => [],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'configuring_subdomain_runcloud',
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'configuring_subdomain_ssl',
            'status' => 'in_progress',
            'message' => 'blog.example.com: Cloudflare DNS and SSL configuration queued.',
        ]);

        Queue::assertPushed(ConfigureRunCloudSubdomainSsl::class, fn (ConfigureRunCloudSubdomainSsl $job) => $job->deployment->is($deployment)
            && $job->subdomain === 'blog.example.com'
            && $job->domainId === 999);
    }

    public function test_it_queues_ssl_repair_for_an_existing_runcloud_subdomain(): void
    {
        $user = User::factory()->create();
        Server::factory()->create(['server_id' => 10, 'ip_address' => '192.0.2.10']);
        $deployment = $this->createDeployment([
            'user_id' => $user->id,
            'server_id' => 10,
            'runcloud_web_app_id' => 77,
        ]);

        $sftp = Mockery::mock(SftpFileService::class);
        $sftp->shouldReceive('fileExists')->andReturnUsing(fn (string $host, string $path) => ! str_contains($path, '.bak-'));
        $sftp->shouldReceive('readFile')->once()->andReturn("<IfModule mod_rewrite.c>\nRewriteEngine On\n</IfModule>\n");
        $sftp->shouldReceive('getFilePermissions')->once()->andReturn(0644);
        $sftp->shouldReceive('writeFile')->twice();
        $this->app->instance(SftpFileService::class, $sftp);

        Config::set('services.runcloud.base_url', 'https://manage.runcloud.io/api/v3');
        Config::set('services.runcloud.api_key', 'test-key');
        Config::set('services.runcloud.api_secret', 'test-secret');
        Queue::fake([ConfigureRunCloudSubdomainSsl::class]);
        Http::preventStrayRequests();
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains' => Http::response([
                'data' => [['id' => 999, 'name' => 'blog.example.com', 'type' => 'alias']],
            ]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/deployments/{$deployment->id}/subdomain-htaccess/apply", ['folders' => ['blog-sub']])
            ->assertOk()
            ->assertJsonPath('data.runcloud.skipped.0.ssl', 'queued')
            ->assertJsonPath('data.runcloud.skipped.0.id', 999);

        Queue::assertPushed(ConfigureRunCloudSubdomainSsl::class, fn (ConfigureRunCloudSubdomainSsl $job) => $job->deployment->is($deployment)
            && $job->subdomain === 'blog.example.com'
            && $job->domainId === 999);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('deployment_logs', [
            'deployment_id' => $deployment->id,
            'step_name' => 'configuring_subdomain_ssl',
            'status' => 'in_progress',
        ]);
    }

    public function test_runcloud_subdomain_is_not_called_if_htaccess_fails(): void
    {
        $user = User::factory()->create();
        Server::factory()->create([
            'server_id' => 10,
            'ip_address' => '192.0.2.10',
        ]);
        $deployment = $this->createDeployment([
            'user_id' => $user->id,
            'server_id' => 10,
            'runcloud_web_app_id' => 77,
        ]);

        $sftp = Mockery::mock(SftpFileService::class);
        $sftp->shouldReceive('fileExists')->andReturnFalse(); // File missing!

        $this->app->instance(SftpFileService::class, $sftp);

        Http::fake();
        Queue::fake([ConfigureRunCloudSubdomainSsl::class]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/deployments/{$deployment->id}/subdomain-htaccess/apply", [
                'folders' => ['blog-sub'],
            ]);

        $response->assertStatus(422);

        // RunCloud HTTP API should never have been contacted
        Http::assertNothingSent();
        Queue::assertNotPushed(ConfigureRunCloudSubdomainSsl::class);
    }
}
