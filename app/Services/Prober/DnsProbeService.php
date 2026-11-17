<?php

declare(strict_types=1);

namespace App\Services\Prober;

class DnsProbeService
{
    public function __construct(
        protected HttpProbeService $httpProbeService
    ) {}

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
    public function probeMultiple(string $domain, array $ips, array $protocols = ['http', 'https'], int $timeout = 15): array
    {
        $domain = strtolower(trim($domain));
        $domainBase = preg_replace('/^www\./', '', $domain);
        $domainVariants = array_values(array_unique([
            $domainBase,
            'www.' . $domainBase,
        ]));

        $mh = curl_multi_init();
        $handles = [];
        $immediateResults = [];
        $fallbackSequential = false;

        foreach ($ips as $ip) {
            foreach ($domainVariants as $targetHost) {
                foreach ($protocols as $protocol) {
                    $handleOrError = $this->httpProbeService->createHandle($targetHost, $ip, $protocol, $timeout);

                    if (is_array($handleOrError)) {
                        $immediateResults[] = $handleOrError;
                        continue;
                    }

                    if (! ($handleOrError instanceof \CurlHandle) && ! is_resource($handleOrError)) {
                        $fallbackSequential = true;
                        break 3;
                    }

                    $key = (int) $handleOrError;
                    curl_multi_add_handle($mh, $handleOrError);
                    $handles[$key] = [
                        'handle' => $handleOrError,
                        'ip' => $ip,
                        'target_host' => $targetHost,
                        'protocol' => $protocol,
                        'start_time' => microtime(true),
                    ];
                }
            }
        }

        if ($fallbackSequential) {
            curl_multi_close($mh);
            $results = [];
            foreach ($ips as $ip) {
                foreach ($domainVariants as $targetHost) {
                    foreach ($protocols as $protocol) {
                        $results[] = $this->httpProbeService->probe($targetHost, $ip, $protocol, $timeout);
                    }
                }
            }

            $summaries = $this->buildIpSummaries($results, $domain);

            return [
                'domain' => $domain,
                'results' => $results,
                'summaries' => $summaries,
            ];
        }

        // Execute all handles in parallel
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc === CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc === CURLM_OK) {
            if (curl_multi_select($mh, 0.5) === -1) {
                usleep(5000);
            }
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc === CURLM_CALL_MULTI_PERFORM);
        }

        // Collect results
        $results = $immediateResults;

        foreach ($handles as $item) {
            $ch = $item['handle'];
            $ip = $item['ip'];
            $protocol = $item['protocol'];
            $durationMs = (int) round((microtime(true) - $item['start_time']) * 1000);

            $response = curl_multi_getcontent($ch);
            $parsed = $this->httpProbeService->parseResponse($ch, $response, $ip, $protocol, $durationMs);
            $parsed['target_host'] = $item['target_host'] ?? null;
            $results[] = $parsed;

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        $summaries = $this->buildIpSummaries($results, $domain);

        return [
            'domain' => $domain,
            'results' => $results,
            'summaries' => $summaries,
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
                    'raw_results' => [],
                ];
            }

            $proto = strtolower($res['protocol'] ?? 'http');
            $status = $res['status'] ?? null;
            $grouped[$ip]['raw_results'][] = $res;

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
            $isParkingOrSale = false;
            $combinedText = strtolower($title . ' ' . substr($body, 0, 1000));
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
                    $isParkingOrSale = true;
                    break;
                }
            }

            // Check if title or body mentions the domain or brand keywords
            $matchesDomainKeywords = function (string $text) use ($cleanDomain, $domainBase, $domainNameOnly, $domainWordsSpace, $domainWordsJoined, $domainTokens): bool {
                $lower = strtolower($text);
                if (str_contains($lower, $cleanDomain) || str_contains($lower, $domainBase)) {
                    return true;
                }
                if (strlen($domainNameOnly) >= 4 && str_contains($lower, $domainNameOnly)) {
                    return true;
                }
                if (strlen($domainWordsSpace) >= 4 && str_contains($lower, $domainWordsSpace)) {
                    return true;
                }
                if (strlen($domainWordsJoined) >= 4 && str_contains($lower, $domainWordsJoined)) {
                    return true;
                }

                // Also match normalized alphanumeric text (e.g. "qatar gulf news" matches "qatargulfnews")
                $normalizedText = preg_replace('/[^a-z0-9]+/', '', $lower);
                if (strlen($domainWordsJoined) >= 4 && str_contains($normalizedText, $domainWordsJoined)) {
                    return true;
                }

                // Check if major tokens from domain name are in the text
                if (! empty($domainTokens)) {
                    $matchedCount = 0;
                    foreach ($domainTokens as $token) {
                        if (strlen($token) >= 3 && str_contains($lower, $token)) {
                            $matchedCount++;
                        }
                    }
                    if ($matchedCount >= count($domainTokens)) {
                        return true;
                    }
                }

                return false;
            };


            if (! empty($title)) {
                if ($isParkingOrSale) {
                    $notes[] = 'Parking / For Sale page';
                    $score += 1;
                } elseif ($matchesDomainKeywords($title)) {
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

            // Also check body text for domain or brand keywords if NOT parking/for sale
            if (! $isContentRelated && ! $isParkingOrSale && ! empty($body) && $matchesDomainKeywords($body)) {
                $isContentRelated = true;
                $score += 2;
                $notes[] = 'Body mentions domain';
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
            if ($isRedirectOnly) {
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
                if (($httpsErr && str_contains(strtolower($httpsErr), 'timeout')) || ($httpErr && str_contains(strtolower($httpErr), 'timeout'))) {
                    $isTimeout = true;
                    $score = 0;
                    $notes = ['No response — connect-timeout'];
                } else {
                    $isBlocked = true;
                    $score = 1;
                    $notes = ['TCP 443+80 — server alive · Likely rate-limited/blocked'];
                }
            }

            // Verdict assignment
            if ($isTimeout || ($score === 0 && $bothNoStatus)) {
                $verdict = 'Dead';
            } elseif ($isBlocked) {
                $verdict = 'Live (blocked)';
            } elseif ($isParkingOrSale) {
                $verdict = 'Parking / For Sale';
            } elseif ($isContentRelated && $has2xx) {
                $verdict = 'Likely old site';
            } elseif ($score >= 4 && ! $isParkingOrSale) {
                $verdict = 'Possible';
            } elseif ($has2xx || $is404 || $score >= 2) {
                $verdict = 'Server live';
            } else {
                $verdict = 'Unlikely';
            }

            $summaries[] = [
                'ip' => $ip,
                'verdict' => $verdict,
                'score' => $score,
                'is_working' => $has2xx,
                'is_https_2xx' => is_numeric($data['https'] ?? null) && (int) $data['https'] >= 200 && (int) $data['https'] < 300,
                'is_parking' => $isParkingOrSale,
                'is_content_related' => $isContentRelated && ! $data['is_empty'] && ! $isParkingOrSale,
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
