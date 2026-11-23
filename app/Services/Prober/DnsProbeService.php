<?php

declare(strict_types=1);

namespace App\Services\Prober;

class DnsProbeService
{
    public function __construct(
        protected HttpProbeService $httpProbeService
    ) {}

    /**
     * Parse raw historical IP input text (which may contain tabular records with dates/years)
     * and filter records to only include IPs within the most recent 1-year period.
     * If the total number of unique IPs is 15 or below, the 1-year rule is bypassed and all distinct IPs are kept.
     *
     * Finds the latest year present across all records and includes all records dated
     * from the latest year back to (latestYear - 1). Any records older than that 1-year window
     * are excluded.
     *
     * @param string $rawInput
     * @return array<int, string>
     */
    public function filterHistoricalIpsOneYearApart(string $rawInput): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawInput));
        $parsedRecords = [];
        $plainIps = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Find IPv4 in the line
            if (! preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $line, $ipMatch)) {
                continue;
            }

            $ip = $ipMatch[0];

            // Match full date or 4-digit year (e.g. 2026-11-17, 2026/11/17, 2026.11.17, or year 1990-2099)
            $year = null;
            if (preg_match('/\b(19\d\d|20\d\d)(?:[-.\/]\d{1,2}[-.\/]\d{1,2})?\b/', $line, $dateMatch)) {
                $year = (int) $dateMatch[1];
            }

            $parsedRecords[] = [
                'ip' => $ip,
                'year' => $year,
            ];
            $plainIps[] = $ip;
        }

        if (empty($parsedRecords)) {
            return [];
        }

        $uniquePlainIps = array_values(array_unique($plainIps));

        // If total unique IPs are 15 or below, do not apply the 1-year rule
        if (count($uniquePlainIps) <= 15) {
            return $uniquePlainIps;
        }

        // Find all detected years
        $years = array_filter(array_column($parsedRecords, 'year'), fn ($y) => $y !== null);

        // If no years could be parsed from the records at all, fallback to distinct IPs in original order
        if (empty($years)) {
            return $uniquePlainIps;
        }

        // Determine the latest available year dynamically and set a 1-year lookback window
        $latestYear = max($years);
        $minAllowedYear = $latestYear - 1;

        $selectedIps = [];
        foreach ($parsedRecords as $record) {
            if ($record['year'] !== null && $record['year'] >= $minAllowedYear) {
                $selectedIps[] = $record['ip'];
            }
        }

        return array_values(array_unique($selectedIps));
    }

    /**
     * Probe a single IP against the specified domain and protocol.
     *
     * @param string $domain
     * @param string $ip
     * @param string $protocol
     * @param int $timeout
     * @return array<string, mixed>
     */
    public function probeSingle(string $domain, string $ip, string $protocol = 'https', int $timeout = 15): array
    {
        $result = $this->httpProbeService->probe($domain, $ip, $protocol, $timeout);

        return [
            'domain' => $domain,
            'results' => [$result],
        ];
    }

    /**
     * Probe a list of IPs and protocols against the specified domain concurrently.
     *
     * @param string $domain
     * @param array<int, string> $ips
     * @param array<int, string> $protocols
     * @param int $timeout
     * @return array{
     *     domain: string,
     *     results: array<int, array<string, mixed>>,
     *     summaries: array<int, array<string, mixed>>
     * }
     */
    /**
     * Probe a list of IPs and protocols against the specified domain using a controlled concurrency pool.
     *
     * @param string $domain
     * @param array<int, string> $ips
     * @param array<int, string> $protocols
     * @param int $timeout
     * @param int $maxConcurrent Maximum open sockets at any given time (defaults to 6 for safe networking)
     * @return array{
     *     domain: string,
     *     results: array<int, array<string, mixed>>,
     *     summaries: array<int, array<string, mixed>>
     * }
     */
    public function probeMultiple(string $domain, array $ips, array $protocols = ['http', 'https'], int $timeout = 10, int $maxConcurrent = 6): array
    {
        $domain = strtolower(trim($domain));
        $domainBase = preg_replace('/^www\./', '', $domain);
        $domainVariants = array_values(array_unique([
            $domainBase,
            'www.' . $domainBase,
        ]));

        // Build list of all tasks to execute
        $queue = [];
        foreach ($ips as $ip) {
            foreach ($domainVariants as $targetHost) {
                foreach ($protocols as $protocol) {
                    $queue[] = [
                        'ip' => $ip,
                        'target_host' => $targetHost,
                        'protocol' => $protocol,
                    ];
                }
            }
        }

        $results = [];
        $activeHandles = [];
        $mh = curl_multi_init();

        // Helper to launch next task from queue into the multi handle
        $enqueueNext = function () use ($mh, &$queue, &$activeHandles, $domain, $timeout, &$results): bool {
            while (! empty($queue)) {
                $task = array_shift($queue);
                $ip = $task['ip'];
                $targetHost = $task['target_host'];
                $protocol = $task['protocol'];

                $handleOrError = $this->httpProbeService->createHandle($targetHost, $ip, $protocol, $timeout);

                if (is_array($handleOrError)) {
                    $results[] = $handleOrError;
                    continue;
                }

                if (! ($handleOrError instanceof \CurlHandle) && ! is_resource($handleOrError)) {
                    continue;
                }

                $key = (int) $handleOrError;
                curl_multi_add_handle($mh, $handleOrError);
                $activeHandles[$key] = [
                    'handle' => $handleOrError,
                    'ip' => $ip,
                    'target_host' => $targetHost,
                    'protocol' => $protocol,
                    'start_time' => microtime(true),
                ];

                return true;
            }

            return false;
        };

        // Seed initial pool up to $maxConcurrent sockets
        for ($i = 0; $i < $maxConcurrent; $i++) {
            if (! $enqueueNext()) {
                break;
            }
        }

        // Run event loop until all handles and queue items are exhausted
        do {
            $mrc = curl_multi_exec($mh, $active);
            if ($mrc === CURLM_CALL_MULTI_PERFORM) {
                continue;
            }

            // Check for completed transfers
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $key = (int) $ch;

                if (isset($activeHandles[$key])) {
                    $item = $activeHandles[$key];
                    $durationMs = (int) round((microtime(true) - $item['start_time']) * 1000);
                    $response = curl_multi_getcontent($ch);
                    $parsed = $this->httpProbeService->parseResponse($ch, $response, $item['ip'], $item['protocol'], $durationMs);
                    $parsed['target_host'] = $item['target_host'];
                    $results[] = $parsed;

                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($activeHandles[$key]);

                    // Immediately replenish the pool with the next socket
                    $enqueueNext();
                }
            }

            if ($active && $mrc === CURLM_OK) {
                if (curl_multi_select($mh, 0.2) === -1) {
                    usleep(5000);
                }
            }
        } while (($active > 0 || ! empty($activeHandles) || ! empty($queue)) && $mrc === CURLM_OK);

        curl_multi_close($mh);

        $summaries = $this->buildIpSummaries($results, $domain);

        return [
            'domain' => $domain,
            'results' => $results,
            'summaries' => $summaries,
        ];
    }

    /**
     * Check if a given domain text matches domain or brand keywords.
     */
    public function matchesDomainKeywords(string $domain, string $text): bool
    {
        $cleanDomain = strtolower(trim($domain));
        $domainBase = preg_replace('/^www\./', '', $cleanDomain);
        $domainParts = explode('.', $domainBase);
        $domainNameOnly = $domainParts[0] ?? $domainBase;
        $domainWordsSpace = str_replace(['-', '_'], ' ', $domainNameOnly);
        $domainWordsJoined = str_replace(['-', '_', ' '], '', $domainNameOnly);
        $domainTokens = array_filter(preg_split('/[^a-z0-9]+/', $domainNameOnly) ?: []);

        // Also extract tokens from PascalCase / camelCase domain (e.g. CurrentFishAndOyster -> current, fish, oyster)
        $rawBase = preg_replace('/^www\./i', '', trim($domain));
        $rawNameOnly = explode('.', $rawBase)[0] ?? $rawBase;
        $camelSpaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $rawNameOnly);
        $camelWords = array_values(array_filter(preg_split('/[^a-zA-Z0-9]+/', (string) $camelSpaced) ?: []));
        $camelTokens = array_map('strtolower', $camelWords);

        // Normalize text and normalize '&' to 'and'
        $lower = strtolower($text);
        $textWithAnd = str_ireplace('&', 'and', $lower);

        if (str_contains($lower, $cleanDomain) || str_contains($lower, $domainBase)) {
            return true;
        }
        if (strlen($domainNameOnly) >= 4 && (str_contains($lower, $domainNameOnly) || str_contains($textWithAnd, $domainNameOnly))) {
            return true;
        }
        if (strlen($domainWordsSpace) >= 4 && (str_contains($lower, $domainWordsSpace) || str_contains($textWithAnd, $domainWordsSpace))) {
            return true;
        }
        if (strlen($domainWordsJoined) >= 4 && (str_contains($lower, $domainWordsJoined) || str_contains($textWithAnd, $domainWordsJoined))) {
            return true;
        }

        $normalizedText = preg_replace('/[^a-z0-9]+/', '', $lower);
        $normalizedTextWithAnd = preg_replace('/[^a-z0-9]+/', '', $textWithAnd);
        if (strlen($domainWordsJoined) >= 4 && (str_contains($normalizedText, $domainWordsJoined) || str_contains($normalizedTextWithAnd, $domainWordsJoined))) {
            return true;
        }

        // Check tokens from punctuation-separated domain (e.g. qatar-gulfnews)
        if (! empty($domainTokens) && count($domainTokens) > 1) {
            $matchedCount = 0;
            foreach ($domainTokens as $token) {
                if (strlen($token) >= 3 && (str_contains($lower, $token) || str_contains($textWithAnd, $token))) {
                    $matchedCount++;
                }
            }
            if ($matchedCount >= count($domainTokens)) {
                return true;
            }
        }

        // Check tokens from camelCase / PascalCase domain (e.g. CurrentFishAndOyster)
        if (! empty($camelTokens) && count($camelTokens) > 1) {
            $meaningfulTokens = array_filter($camelTokens, fn ($t) => strlen($t) >= 3 && ! in_array($t, ['and', 'the', 'for', 'with', 'com', 'net', 'org'], true));
            if (! empty($meaningfulTokens)) {
                $matchedCount = 0;
                foreach ($meaningfulTokens as $token) {
                    if (str_contains($lower, $token) || str_contains($textWithAnd, $token)) {
                        $matchedCount++;
                    }
                }
                if ($matchedCount >= count($meaningfulTokens)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a text represents a parking, for-sale, or placeholder page.
     */
    public function isParkingOrSale(string $text): bool
    {
        $combinedText = strtolower($text);
        $parkingPatterns = [
            'for sale',
            'buy this domain',
            'is for sale',
            'parking page',
            'domain parking',
            'parked domain',
            'parked free',
            'resources and information',
            'resources and information.',
            'spaceship.com',
            'dan.com',
            'sedo.com',
            'godaddy.com',
            'namecheap.com',
            'hugedomains',
            'afternic',
            'bodis',
            'domain is registered',
            'inquire about this domain',
            'related searches',
        ];

        foreach ($parkingPatterns as $pattern) {
            if (str_contains($combinedText, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch a live domain over HTTP/HTTPS and check if it returns working 2xx status and content related to domain.
     *
     * @param string $domain
     * @param int $timeout
     * @return array{is_working: bool, is_content_related: bool, title: ?string, status: ?int, error: ?string}
     */
    public function verifyLiveDomainContent(string $domain, int $timeout = 10): array
    {
        $domain = strtolower(trim($domain));
        $domainBase = preg_replace('/^www\./', '', $domain);
        $domainVariants = array_values(array_unique([
            'https://' . $domainBase . '/',
            'https://www.' . $domainBase . '/',
            'http://' . $domainBase . '/',
            'http://www.' . $domainBase . '/',
        ]));

        foreach ($domainVariants as $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 4,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min($timeout, 5),
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Prober/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);

            $response = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($statusCode >= 200 && $statusCode < 300 && is_string($response) && strlen($response) > 0) {
                $extracted = app(HtmlExtractorService::class)->extract($response);
                $title = $extracted['title'] ?? '';
                $body = $extracted['body_text'] ?? '';
                $combined = $title . ' ' . substr($body, 0, 1000);

                if (! $this->isParkingOrSale($combined) && ! $extracted['is_empty_or_default']) {
                    // Check title, body, description, canonical, and h1
                    $extendedContent = implode(' ', array_filter([
                        $title,
                        $extracted['description'] ?? '',
                        $extracted['canonical'] ?? '',
                        implode(' ', $extracted['h1'] ?? []),
                        $body,
                    ]));

                    $isRelated = $this->matchesDomainKeywords($domain, $extendedContent);
                    if ($isRelated) {
                        $parsedHost = parse_url($url, PHP_URL_HOST);
                        $detectedVersion = ($parsedHost && str_starts_with(strtolower($parsedHost), 'www.')) ? 'www' : 'non-www';

                        return [
                            'is_working' => true,
                            'is_content_related' => true,
                            'title' => $title,
                            'status' => $statusCode,
                            'url' => $url,
                            'detected_version' => $detectedVersion,
                            'error' => null,
                        ];
                    }
                }
            }
        }

        return [
            'is_working' => false,
            'is_content_related' => false,
            'title' => null,
            'status' => null,
            'url' => null,
            'detected_version' => null,
            'error' => 'No working related content found across domain variants.',
        ];
    }

    /**
     * Check if a given domain + IP has a valid sitemap.xml.
     *
     * @param string $domain
     * @param string $ip
     * @param int $timeout
     * @return bool
     */
    public function checkSitemapExists(string $domain, string $ip, int $timeout = 10): bool
    {
        $domain = strtolower(trim($domain));
        $domainBase = preg_replace('/^www\./', '', $domain);
        $domainVariants = array_values(array_unique([
            $domainBase,
            'www.' . $domainBase,
        ]));
        $protocols = ['https', 'http'];

        foreach ($domainVariants as $targetHost) {
            foreach ($protocols as $protocol) {
                $port = $protocol === 'https' ? 443 : 80;
                $url = "{$protocol}://{$targetHost}/sitemap.xml";
                $resolveRule = "{$targetHost}:{$port}:{$ip}";

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RESOLVE => [$resolveRule],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_NOBODY => true, // HEAD request
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => min($timeout, 5),
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Prober/1.0',
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);

                curl_exec($ch);
                $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($statusCode >= 200 && $statusCode < 300) {
                    // Return true if HTTP 2xx
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Group per-protocol results by IP and score/classify each candidate IP.
     *
     * @param array<int, array<string, mixed>> $results
     * @param string $domain
     * @return array<int, array<string, mixed>>
     */
    public function buildIpSummaries(array $results, string $domain): array
    {
        $grouped = [];

        foreach ($results as $res) {
            $ip = $res['ip'];
            if (! isset($grouped[$ip])) {
                $grouped[$ip] = [
                    'ip' => $ip,
                    'https' => null,
                    'http' => null,
                    'https_res' => null,
                    'http_res' => null,
                    'title' => null,
                    'server' => null,
                    'is_cloudflare' => false,
                    'is_empty' => false,
                    'is_redirect' => false,
                    'body_len' => 0,
                    'body_text' => '',
                    'ssl_valid' => null,
                    'description' => null,
                    'canonical' => null,
                    'h1' => [],
                    'raw_results' => [],
                ];
            }

            $proto = strtolower($res['protocol'] ?? 'http');
            $status = $res['status'] ?? null;
            $grouped[$ip]['raw_results'][] = $res;

            if (! empty($res['description']) && empty($grouped[$ip]['description'])) {
                $grouped[$ip]['description'] = $res['description'];
            }
            if (! empty($res['canonical']) && empty($grouped[$ip]['canonical'])) {
                $grouped[$ip]['canonical'] = $res['canonical'];
            }
            if (! empty($res['h1']) && is_array($res['h1']) && empty($grouped[$ip]['h1'])) {
                $grouped[$ip]['h1'] = $res['h1'];
            }

            // Preserve best (2xx preferred over redirect/error) status per protocol
            if ($proto === 'https') {
                if ($grouped[$ip]['https'] === null || ($status >= 200 && $status < 300) || ($status && $grouped[$ip]['https'] >= 400)) {
                    $grouped[$ip]['https'] = $status;
                    $grouped[$ip]['https_res'] = $res;
                }
            } else {
                if ($grouped[$ip]['http'] === null || ($status >= 200 && $status < 300) || ($status && $grouped[$ip]['http'] >= 400)) {
                    $grouped[$ip]['http'] = $status;
                    $grouped[$ip]['http_res'] = $res;
                }
            }

            if (! empty($res['server']) && empty($grouped[$ip]['server'])) {
                $grouped[$ip]['server'] = $res['server'];
            }

            // Prefer a title that mentions content over an empty/redirect title
            if (! empty($res['title'])) {
                if (empty($grouped[$ip]['title']) || strlen($res['title']) > strlen($grouped[$ip]['title'])) {
                    $grouped[$ip]['title'] = $res['title'];
                }
            }

            if (! empty($res['is_cloudflare_challenge'])) {
                $grouped[$ip]['is_cloudflare'] = true;
            }

            $bodyLen = strlen($res['body_text'] ?? '');
            if ($bodyLen > $grouped[$ip]['body_len']) {
                $grouped[$ip]['body_len'] = $bodyLen;
                $grouped[$ip]['body_text'] = $res['body_text'] ?? '';
            }

            if (! empty($res['is_empty_or_default'])) {
                // Only mark empty if we haven't seen a non-empty body
                if ($grouped[$ip]['body_len'] === 0) {
                    $grouped[$ip]['is_empty'] = true;
                }
            } else {
                $grouped[$ip]['is_empty'] = false;
            }

            if (! empty($res['is_redirect'])) {
                $grouped[$ip]['is_redirect'] = true;
            }

            if ($proto === 'https' && isset($res['ssl_valid'])) {
                $grouped[$ip]['ssl_valid'] = $res['ssl_valid'];
            }

            if (! empty($res['body_text']) && strlen($res['body_text']) > strlen($grouped[$ip]['body_text'])) {
                $grouped[$ip]['body_text'] = $res['body_text'];
            }
        }

        $summaries = [];
        $cleanDomain = strtolower($domain);
        $domainBase = preg_replace('/^www\./', '', $cleanDomain);
        $domainParts = explode('.', $domainBase);
        $domainNameOnly = $domainParts[0] ?? $domainBase; // e.g. "qatar-gulfnews"
        // Also domain with hyphens/underscores replaced by space, e.g. "qatar gulfnews"
        $domainWordsSpace = str_replace(['-', '_'], ' ', $domainNameOnly); // e.g. "qatar gulfnews"
        // And domain without hyphens/underscores/spaces, e.g. "qatargulfnews"
        $domainWordsJoined = str_replace(['-', '_', ' '], '', $domainNameOnly);
        // Clean alphanumerics only from words
        $domainTokens = array_filter(preg_split('/[^a-z0-9]+/', $domainNameOnly));

        foreach ($grouped as $ip => $data) {
            $score = 0;
            $notes = [];
            $httpsRes = $data['https_res'];
            $httpRes = $data['http_res'];

            $hasHttps2xx = ($data['https'] && $data['https'] >= 200 && $data['https'] < 300);
            $hasHttp2xx = ($data['http'] && $data['http'] >= 200 && $data['http'] < 300);
            $has2xx = $hasHttps2xx || $hasHttp2xx;

            $hasHttpsHandshakeOk = ($httpsRes && ($httpsRes['ssl_valid'] === true || $data['https'] !== null));

            // Check if any handle for this IP encountered a block/challenge, 429, or 403 status
            $hasBlockedStatus = false;
            $hasBlockedError = false;
            $allTimeouts = true;
            $hasAnyError = false;

            foreach ($data['raw_results'] as $raw) {
                $status = $raw['status'] ?? null;
                $err = strtolower($raw['error'] ?? '');
                if ($status === 403 || $status === 429) {
                    $hasBlockedStatus = true;
                }
                if ($raw['is_cloudflare_challenge'] ?? false) {
                    $hasBlockedError = true;
                }
                if (! empty($err)) {
                    $hasAnyError = true;
                    if (! str_contains($err, 'timeout')) {
                        $allTimeouts = false;
                    }
                    if (str_contains($err, 'refused') || str_contains($err, 'reset') || str_contains($err, 'ssl_error') || str_contains($err, 'handshake') || str_contains($err, 'forbidden') || str_contains($err, 'blocked')) {
                        $hasBlockedError = true;
                    }
                }
            }

            // 1. Status 2xx base score
            if ($has2xx) {
                $score += 2;
                $notes[] = '2xx response';
            } elseif (($data['https'] && $data['https'] >= 300 && $data['https'] < 400) ||
                      ($data['http'] && $data['http'] >= 300 && $data['http'] < 400)) {
                $score += 2;
                $notes[] = 'Redirect response';
            }

            // 2. HTTPS handshake OK
            if ($hasHttps2xx && $hasHttpsHandshakeOk) {
                $score += 1;
                $notes[] = 'HTTPS handshake OK';
            } elseif ($data['ssl_valid'] === false) {
                $notes[] = 'SSL mismatch / invalid';
            }

            // 3. Title analysis
            $title = $data['title'] ?? '';
            $body = $data['body_text'] ?? '';

            $isContentRelated = false;

            // Check for parking / for sale / placeholder indicators
            $combinedText = ($title ?? '') . ' ' . substr($body ?? '', 0, 1000);
            $isParkingOrSale = $this->isParkingOrSale($combinedText);

            if (! empty($title)) {
                if ($isParkingOrSale) {
                    $notes[] = 'Parking / For Sale page';
                    $score += 1;
                } elseif ($this->matchesDomainKeywords($domain, $title)) {
                    $score += 4;
                    $notes[] = 'Title mentions domain';
                    $isContentRelated = true;
                } elseif (stripos($title, '302 found') !== false) {
                    $notes[] = 'Title: "302 Found..."';
                } elseif (stripos($title, '404') !== false) {
                    $notes[] = 'Title: "404..."';
                } elseif (stripos($title, 'website unavailable') !== false) {
                    $notes[] = 'Title: "Website Unavailable..."';
                } else {
                    $notes[] = 'Title: "' . substr($title, 0, 25) . '..."';
                }
            }

            // Check body text and metadata (description, canonical, h1) for domain or brand keywords if NOT parking/for sale
            $extraMetadata = implode(' ', array_filter([
                $data['description'] ?? '',
                $data['canonical'] ?? '',
                implode(' ', $data['h1'] ?? []),
                $body,
            ]));

            if (! $isContentRelated && ! $isParkingOrSale && ! empty($extraMetadata) && $this->matchesDomainKeywords($domain, $extraMetadata)) {
                $isContentRelated = true;
                $score += 2;
                $notes[] = 'Content/Meta mentions domain';
            }

            // 4. Body length
            if ($data['body_len'] >= 250) {
                $score += 2;
                $notes[] = 'Substantial body';
            } elseif ($has2xx && $data['body_len'] < 250) {
                $notes[] = 'Thin body';
            }

            if ($data['is_cloudflare']) {
                $notes[] = 'Cloudflare challenge';
            }

            // 5. Handle redirect / 404 / error cases
            $is404 = ($data['http'] === 404 || $data['https'] === 404);
            if ($is404 && ! $has2xx) {
                $score = 2;
                $notes = array_merge(['404 (server up, page missing)'], $notes);
            }

            $isRedirectOnly = (($data['http'] >= 300 && $data['http'] < 400) || ($data['https'] >= 300 && $data['https'] < 400)) && ! $has2xx;
            if ($isRedirectOnly && ! $hasBlockedStatus) {
                $score = max($score, 2);
                $notes = array_merge(['Redirect response'], $notes);
            }

            // 6. Blocked / Connection error handling
            $httpsErr = $httpsRes['error'] ?? null;
            $httpErr = $httpRes['error'] ?? null;
            $bothNoStatus = ($data['https'] === null && $data['http'] === null);

            $isBlocked = false;
            $isTimeout = false;

            if ($bothNoStatus) {
                if ($allTimeouts && $hasAnyError) {
                    $isTimeout = true;
                    $score = 0;
                    $notes = ['No response — connect-timeout'];
                } else {
                    $isBlocked = true;
                    $score = 1;
                    $notes = ['TCP 443+80 — server alive · Likely rate-limited/blocked'];
                }
            } elseif ($hasBlockedStatus && ! $has2xx) {
                // If the IP returned 403 Forbidden, 429 Too Many Requests, or Cloudflare challenge, mark as blocked
                $isBlocked = true;
                $score = max($score, 1);
                $notes = array_merge(['Rate-limited / 403 / 429 — server alive'], $notes);
            } elseif ($hasBlockedError && ! $has2xx) {
                $isBlocked = true;
                $score = max($score, 1);
                $notes = array_merge(['Connection refused or blocked by host'], $notes);
            } elseif ($isRedirectOnly && $data['https'] === null && ! $has2xx) {
                // Port 80 gave a redirect (e.g. 301/302) but HTTPS (port 443) connection failed / refused / blocked
                $isBlocked = true;
                $score = max($score, 1);
                $notes = array_merge(['Port 80 redirect, but HTTPS failed/blocked'], $notes);
            }

            // Verdict assignment
            if ($isParkingOrSale) {
                $verdict = 'Parking / For Sale';
            } elseif ($isBlocked) {
                $verdict = 'Live (blocked)';
            } elseif ($isTimeout || ($score === 0 && $bothNoStatus)) {
                $verdict = 'Dead';
            } elseif ($isContentRelated && $has2xx) {
                $verdict = 'Likely old site';
            } elseif ($score >= 4 && ! $isParkingOrSale && ! $isBlocked) {
                $verdict = 'Possible';
            } elseif (($has2xx || $is404 || $score >= 2) && ! $isBlocked) {
                $verdict = 'Server live';
            } else {
                $verdict = 'Unlikely';
            }

            // Detect working version (www vs non-www) based on successful responses or redirects
            $detectedVersion = null;
            $workingTargetHost = null;

            // Prioritize 2xx responses (HTTPS then HTTP)
            foreach ($data['raw_results'] as $raw) {
                $status = $raw['status'] ?? null;
                if ($status >= 200 && $status < 300) {
                    $workingTargetHost = $raw['target_host'] ?? null;
                    if ($raw['protocol'] === 'https') {
                        break;
                    }
                }
            }

            // If no 2xx, check redirect_url or target_host with redirect status
            if (! $workingTargetHost) {
                foreach ($data['raw_results'] as $raw) {
                    $redir = $raw['redirect_url'] ?? '';
                    if (! empty($redir)) {
                        $parsedHost = parse_url($redir, PHP_URL_HOST);
                        if (! empty($parsedHost) && str_contains(strtolower($parsedHost), strtolower($domainBase))) {
                            $workingTargetHost = $parsedHost;
                            break;
                        }
                    }
                }
            }

            if ($workingTargetHost) {
                $detectedVersion = str_starts_with(strtolower($workingTargetHost), 'www.') ? 'www' : 'non-www';
            }

            $summaries[] = [
                'ip' => $ip,
                'verdict' => $verdict,
                'score' => $score,
                'is_working' => $has2xx,
                'is_https_2xx' => is_numeric($data['https'] ?? null) && (int) $data['https'] >= 200 && (int) $data['https'] < 300,
                'is_parking' => $isParkingOrSale,
                'is_blocked' => $isBlocked,
                'is_content_related' => $isContentRelated && ! $data['is_empty'] && ! $isParkingOrSale,
                'detected_version' => $detectedVersion,
                'https' => $data['https'] ? (string) $data['https'] : '—',
                'http' => $data['http'] ? (string) $data['http'] : '—',
                'title' => $data['title'] ?? '',
                'server' => $data['server'] ?? '',
                'notes' => implode(' · ', array_unique($notes)),
                'raw_results' => $data['raw_results'],
            ];
        }

        // Sort: Highest score first, then prioritize working HTTPS (2xx), then reachability, then IP
        usort($summaries, function (array $a, array $b): int {
            // Prioritize HTTPS 2xx with content-related site
            $aHttpsRelated = ! empty($a['is_https_2xx']) && ! empty($a['is_content_related']) && empty($a['is_parking']) ? 1 : 0;
            $bHttpsRelated = ! empty($b['is_https_2xx']) && ! empty($b['is_content_related']) && empty($b['is_parking']) ? 1 : 0;
            if ($bHttpsRelated !== $aHttpsRelated) {
                return $bHttpsRelated <=> $aHttpsRelated;
            }

            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }

            $hasStatus = function (array $item): int {
                $sHttps = is_numeric($item['https'] ?? null) ? (int) $item['https'] : 0;
                $sHttp = is_numeric($item['http'] ?? null) ? (int) $item['http'] : 0;
                if ($sHttps >= 200 && $sHttps < 300) return 4;
                if ($sHttp >= 200 && $sHttp < 300) return 3;
                if ($sHttps > 0 || $sHttp > 0) return 2;
                return 0;
            };

            $diffStatus = $hasStatus($b) - $hasStatus($a);
            if ($diffStatus !== 0) {
                return $diffStatus;
            }

            return strcmp($a['ip'], $b['ip']);
        });

        return $summaries;
    }
}
