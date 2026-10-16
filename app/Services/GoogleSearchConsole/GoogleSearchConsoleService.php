<?php

namespace App\Services\GoogleSearchConsole;

use App\Jobs\RequestGoogleIndexing;
use App\Services\ApiUsage\ApiUsageContext;
use App\Services\ApiUsage\ApiUsageRecorder;
use App\Services\Cloudflare\CloudflareDnsService;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Indexing;
use Google\Service\Indexing\UrlNotification;
use Google\Service\SearchConsole;
use Google\Service\SiteVerification;
use Google\Service\SiteVerification\SiteVerificationWebResourceGettokenRequest;
use Google\Service\SiteVerification\SiteVerificationWebResourceGettokenRequestSite;
use Google\Service\SiteVerification\SiteVerificationWebResourceResource;
use Google\Service\SiteVerification\SiteVerificationWebResourceResourceSite;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
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
        private ApiUsageContext $usageContext,
    ) {}

    /**
     * Register the domain as a Search Console domain property.
     *
     * Requests a DNS TXT token, publishes it through Cloudflare, verifies
     * ownership, then adds the "sc-domain:" property to Search Console.
     */
    public function verifyProperty(string $domain): bool
    {
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
     * Request indexing for the given URLs via the Indexing API.
     *
     * @param  list<string>  $urls
     * @return array<string, bool> URL => success
     */
    public function requestIndexing(array $urls): array
    {
        $results = [];

        foreach ($urls as $url) {
            $status = $this->publishUrl($url);
            $results[$url] = $status >= 200 && $status < 300;
        }

        return $results;
    }

    /**
     * Publish a single URL_UPDATED notification. Returns the HTTP status code.
     */
    public function publishUrl(string $url): int
    {
        $notification = new UrlNotification;
        $notification->setUrl($url);
        $notification->setType('URL_UPDATED');
        $startedAt = microtime(true);

        try {
            (new Indexing($this->client()))->urlNotifications->publish($notification);
            $this->recordUsage('publish URL for indexing', 200, true, $startedAt);

            return 200;
        } catch (GoogleServiceException $exception) {
            $this->recordUsage('publish URL for indexing', (int) $exception->getCode(), false, $startedAt);
            Log::warning('Google indexing request failed.', [
                'url' => $url,
                'status' => $exception->getCode(),
                'error' => $exception->getErrors()[0]['message'] ?? null,
            ]);

            return (int) $exception->getCode();
        } catch (Throwable $exception) {
            $this->recordUsage('publish URL for indexing', 0, false, $startedAt);

            throw $exception;
        }
    }

    /**
     * Collect all page URLs from a sitemap, following sitemap indexes.
     *
     * @return list<string>
     */
    public function fetchSitemapUrls(string $sitemapUrl, int $depth = 0): array
    {
        if ($depth > 3) {
            return [];
        }

        $response = Http::timeout(30)
            ->withAttributes([
                'api_usage_provider' => 'sitemap',
                'api_usage_operation' => 'fetch_sitemap',
            ])
            ->get($sitemapUrl);
        if (! $response->successful()) {
            Log::warning('Unable to fetch sitemap.', ['sitemap' => $sitemapUrl, 'status' => $response->status()]);

            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $urls = [];

        if ($xml->getName() === 'sitemapindex') {
            foreach ($xml->sitemap as $child) {
                $urls = [...$urls, ...$this->fetchSitemapUrls(trim((string) $child->loc), $depth + 1)];
            }
        } else {
            foreach ($xml->url as $entry) {
                $loc = trim((string) $entry->loc);
                if ($loc !== '') {
                    $urls[] = $loc;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Full workflow: register the domain, submit the sitemap, and queue indexing of every sitemap URL.
     *
     * @return array{verified: bool, sitemap: bool, indexing_queued: bool}
     */
    public function registerDomain(string $domain, string $sitemapUrl = ''): array
    {
        $domain = $this->normalizeDomain($domain);
        $sitemapUrl = $sitemapUrl !== '' ? $sitemapUrl : "https://{$domain}/sitemap.xml";

        $verified = $this->verifyProperty($domain);
        $sitemap = $this->submitSitemap($domain, $sitemapUrl);

        RequestGoogleIndexing::dispatch(
            $domain,
            $sitemapUrl,
            0,
            $this->usageContext->deploymentId(),
        );

        return ['verified' => $verified, 'sitemap' => $sitemap, 'indexing_queued' => true];
    }

    private function client(): GoogleClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $path = (string) config('services.google_search_console.credentials_path');

        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('Google service account credentials file is not configured.');
        }

        $client = new GoogleClient;
        $client->setApplicationName((string) config('app.name'));
        $client->setAuthConfig($path);
        $client->setScopes([
            SiteVerification::SITEVERIFICATION,
            SearchConsole::WEBMASTERS,
            Indexing::INDEXING,
        ]);
        $client->setHttpClient(new Client([
            'timeout' => (int) config('services.google_search_console.timeout', 30),
        ]));

        return $this->client = $client;
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
