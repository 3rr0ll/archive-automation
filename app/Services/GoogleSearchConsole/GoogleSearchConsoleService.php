<?php

namespace App\Services\GoogleSearchConsole;

use App\Services\ApiUsage\ApiUsageRecorder;
use App\Services\Cloudflare\CloudflareDnsService;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\SearchConsole;
use Google\Service\SiteVerification;
use Google\Service\SiteVerification\SiteVerificationWebResourceGettokenRequest;
use Google\Service\SiteVerification\SiteVerificationWebResourceGettokenRequestSite;
use Google\Service\SiteVerification\SiteVerificationWebResourceResource;
use Google\Service\SiteVerification\SiteVerificationWebResourceResourceSite;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GoogleSearchConsoleService
{
    private const VERIFY_ATTEMPTS = 5;

    private const VERIFY_DELAY_SECONDS = 30;

    private ?GoogleClient $client = null;

    public function __construct(
        private CloudflareDnsService $dnsService,
        private ApiUsageRecorder $usageRecorder,
        private GoogleSearchConsoleSettings $settings,
        private GoogleSearchConsoleOAuthService $oauth,
    ) {}

    /**
     * Register the domain as a Search Console domain property.
     *
     * Requests a DNS TXT token, publishes it through Cloudflare, verifies
     * ownership, then adds the "sc-domain:" property to Search Console.
     */
    public function verifyProperty(string $domain): bool
    {
        $this->ensureEnabled();
        $domain = $this->normalizeDomain($domain);
        $verification = new SiteVerification($this->client());

        $tokenSite = new SiteVerificationWebResourceGettokenRequestSite;
        $tokenSite->setType('INET_DOMAIN');
        $tokenSite->setIdentifier($domain);

        $tokenRequest = new SiteVerificationWebResourceGettokenRequest;
        $tokenRequest->setSite($tokenSite);
        $tokenRequest->setVerificationMethod('DNS_TXT');

        $token = (string) $this->call('request verification token', $domain,
            fn () => $verification->webResource->getToken($tokenRequest)->getToken());

        if ($token === '') {
            throw new RuntimeException('Google did not return a verification token.');
        }

        if (! $this->hasTxtRecord($domain, $token)) {
            $this->dnsService->addDnsRecord($domain, 'TXT', $domain, $token, false, 300);
        }

        $resourceSite = new SiteVerificationWebResourceResourceSite;
        $resourceSite->setType('INET_DOMAIN');
        $resourceSite->setIdentifier($domain);

        $resource = new SiteVerificationWebResourceResource;
        $resource->setSite($resourceSite);

        // DNS propagation can lag behind the Cloudflare write, so retry verification.
        for ($attempt = 1; ; $attempt++) {
            $startedAt = microtime(true);
            try {
                $verification->webResource->insert('DNS_TXT', $resource);
                $this->recordUsage('verify domain ownership', 200, true, $startedAt);
                break;
            } catch (GoogleServiceException $exception) {
                $this->recordUsage('verify domain ownership', (int) $exception->getCode(), false, $startedAt);
                if ($attempt >= self::VERIFY_ATTEMPTS) {
                    $this->fail('verify domain ownership', $domain, $exception);
                }
                sleep(self::VERIFY_DELAY_SECONDS);
            } catch (Throwable $exception) {
                $this->recordUsage('verify domain ownership', 0, false, $startedAt);

                throw $exception;
            }
        }

        $searchConsole = new SearchConsole($this->client());
        $this->call('add Search Console property', $domain,
            fn () => $searchConsole->sites->add($this->propertyUrl($domain)));

        Log::info('Google Search Console property registered.', ['domain' => $domain]);

        return true;
    }

    /**
     * Submit a sitemap for the domain property.
     */
    public function submitSitemap(string $domain, string $sitemapUrl = ''): bool
    {
        $this->ensureEnabled();
        $domain = $this->normalizeDomain($domain);
        $sitemapUrl = $sitemapUrl !== '' ? $sitemapUrl : "https://{$domain}/sitemap.xml";

        $searchConsole = new SearchConsole($this->client());
        $this->call('submit sitemap', $domain,
            fn () => $searchConsole->sitemaps->submit($this->propertyUrl($domain), $sitemapUrl));

        Log::info('Sitemap submitted to Google Search Console.', [
            'domain' => $domain,
            'sitemap' => $sitemapUrl,
        ]);

        return true;
    }

    /**
     * Full workflow: register the domain and submit its sitemap for discovery.
     *
     * Google's Indexing API is intentionally not used here because it only
     * supports JobPosting and livestream BroadcastEvent pages. Submitting the
     * sitemap is the supported bulk discovery mechanism for ordinary sites.
     *
     * @return array{verified: bool, sitemap: bool, indexing_queued: bool}
     */
    public function registerDomain(string $domain, string $sitemapUrl = ''): array
    {
        $domain = $this->normalizeDomain($domain);
        $sitemapUrl = $sitemapUrl !== '' ? $sitemapUrl : "https://{$domain}/sitemap.xml";

        $verified = $this->verifyProperty($domain);
        $sitemap = $this->submitSitemap($domain, $sitemapUrl);

        return ['verified' => $verified, 'sitemap' => $sitemap, 'indexing_queued' => false];
    }

    public function isEnabled(): bool
    {
        return $this->settings->clientConfigured()
            && $this->settings->connected()
            && $this->settings->active();
    }

    /**
     * Validate credentials and confirm that the Search Console API is reachable.
     *
     * @return array{accessible_properties: int, account_email: ?string}
     */
    public function testConnection(): array
    {
        $startedAt = microtime(true);

        try {
            $response = (new SearchConsole($this->client()))
                ->sites
                ->listSites();
            $this->recordUsage('test account connection', 200, true, $startedAt);

            return [
                'accessible_properties' => count($response->getSiteEntry() ?? []),
                'account_email' => $this->settings->accountEmail(),
            ];
        } catch (GoogleServiceException $exception) {
            $this->recordUsage('test account connection', (int) $exception->getCode(), false, $startedAt);
            $this->fail('test account connection', $this->settings->accountEmail() ?? 'connected account', $exception);
        } catch (Throwable $exception) {
            $this->recordUsage('test account connection', 0, false, $startedAt);

            throw $exception;
        }
    }

    private function client(): GoogleClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        return $this->client = $this->oauth->authorizedClient();
    }

    private function ensureEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Google Search Console must be configured, connected, and active.');
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function call(string $operation, string $domain, callable $callback): mixed
    {
        $startedAt = microtime(true);

        try {
            $result = $callback();
            $this->recordUsage($operation, 200, true, $startedAt);

            return $result;
        } catch (GoogleServiceException $exception) {
            $this->recordUsage($operation, (int) $exception->getCode(), false, $startedAt);
            $this->fail($operation, $domain, $exception);
        } catch (Throwable $exception) {
            $this->recordUsage($operation, 0, false, $startedAt);

            throw $exception;
        }
    }

    private function recordUsage(string $operation, int $statusCode, bool $successful, float $startedAt): void
    {
        $this->usageRecorder->record(
            provider: 'google_search_console',
            operation: $operation,
            method: 'POST',
            endpoint: 'https://googleapis.com/'.str_replace(' ', '-', $operation),
            statusCode: $statusCode > 0 ? $statusCode : null,
            successful: $successful,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    private function fail(string $operation, string $domain, GoogleServiceException $exception): never
    {
        Log::error('Google Search Console request failed.', [
            'operation' => $operation,
            'domain' => $domain,
            'status' => $exception->getCode(),
            'error' => $exception->getErrors()[0]['message'] ?? null,
        ]);

        throw new RuntimeException("Google Search Console failed to {$operation} for {$domain}.", 0, $exception);
    }

    private function hasTxtRecord(string $domain, string $token): bool
    {
        foreach ($this->dnsService->getDnsRecords($domain) as $record) {
            if (($record['type'] ?? '') === 'TXT' && trim((string) ($record['content'] ?? ''), '"') === $token) {
                return true;
            }
        }

        return false;
    }

    private function propertyUrl(string $domain): string
    {
        return "sc-domain:{$domain}";
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);

        return rtrim(explode('/', $domain)[0], '.');
    }
}
