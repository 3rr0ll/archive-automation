<?php

namespace Tests\Feature;

use App\Services\RunCloud\RunCloudSslService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunCloudSslServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.runcloud.base_url', 'https://manage.runcloud.io/api/v3');
        Config::set('services.runcloud.api_token', 'test-token');
    }

    public function test_it_verifies_and_updates_existing_ssl_after_the_already_installed_403(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/ssl/advanced' => Http::response(['advancedSSL' => true, 'autoSSL' => true]),
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains/999/ssl' => fn (Request $request) => $request->method() === 'POST'
                ? Http::response(['message' => 'You already have SSL installed'], 403)
                : Http::response(['id' => 555, 'method' => 'letsencrypt', 'status' => 'deployed', 'privateKey' => 'must-not-be-returned']),
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains/999/ssl/555' => Http::response(['message' => 'Updated.']),
        ]);

        $result = app(RunCloudSslService::class)->configureAdvancedSsl(10, 77, 'blog.example.com', 999);

        $this->assertSame(['id' => 555, 'method' => 'letsencrypt', 'status' => 'deployed', 'enableHttp' => false, 'enableHsts' => false], $result);
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && $request->url() === 'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains/999/ssl/555'
            && $request['enableHttp'] === false
            && $request['enableHsts'] === false);
    }

    public function test_it_does_not_mark_ssl_configured_if_verification_returns_403(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/ssl/advanced' => Http::response(['advancedSSL' => true, 'autoSSL' => true]),
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains/999/ssl' => fn (Request $request) => $request->method() === 'POST'
                ? Http::response(['message' => 'You already have SSL installed'], 403)
                : Http::response(['message' => 'SSL Advanced not installed!'], 403),
        ]);

        $this->expectExceptionMessage('RunCloud API error (403): SSL Advanced not installed!');

        app(RunCloudSslService::class)->configureAdvancedSsl(10, 77, 'blog.example.com', 999);
    }

    public function test_the_dns_provider_403_is_reported_as_an_actual_failure(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/ssl/advanced' => Http::response(['advancedSSL' => true, 'autoSSL' => true]),
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains/999/ssl' => Http::response(['message' => 'Domain is not found in the selected DNS provider.'], 403),
        ]);

        $this->expectExceptionMessage('RunCloud API error (403): Domain is not found in the selected DNS provider.');

        app(RunCloudSslService::class)->configureAdvancedSsl(10, 77, 'blog.example.com', 999);
    }

    public function test_it_rejects_an_existing_ssl_response_without_a_certificate_id(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/ssl/advanced' => Http::response(['advancedSSL' => true, 'autoSSL' => true]),
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains/999/ssl' => fn (Request $request) => $request->method() === 'POST'
                ? Http::response(['message' => 'You already have SSL installed'], 403)
                : Http::response([]),
        ]);

        $this->expectExceptionMessage('RunCloud reported existing SSL for [blog.example.com], but returned no SSL certificate ID.');

        app(RunCloudSslService::class)->configureAdvancedSsl(10, 77, 'blog.example.com', 999);
    }

    public function test_it_reports_an_existing_ssl_update_failure_with_http_422(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/ssl/advanced' => Http::response(['advancedSSL' => true, 'autoSSL' => true]),
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains/999/ssl' => fn (Request $request) => $request->method() === 'POST'
                ? Http::response(['message' => 'You already have SSL installed'], 403)
                : Http::response(['id' => 555, 'method' => 'letsencrypt']),
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/domains/999/ssl/555' => Http::response(['message' => 'Invalid SSL settings.'], 422),
        ]);

        $this->expectExceptionMessage('RunCloud API error (422): Invalid SSL settings.');

        app(RunCloudSslService::class)->configureAdvancedSsl(10, 77, 'blog.example.com', 999);
    }

    public function test_it_stops_if_advanced_ssl_and_autossl_cannot_be_enabled(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://manage.runcloud.io/api/v3/servers/10/webapps/77/ssl/advanced' => Http::response(['message' => 'Permission denied.'], 403),
        ]);

        $this->expectExceptionMessage('RunCloud could not enable Advanced SSL and AutoSSL for this web application.');

        app(RunCloudSslService::class)->configureAdvancedSsl(10, 77, 'blog.example.com', 999);
    }
}
