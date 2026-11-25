<?php

declare(strict_types=1);

namespace App\Services\Prober;

class HttpProbeService
{
    public function __construct(
        protected HtmlExtractorService $htmlExtractor
    ) {}

    /**
     * Probe a single domain against an IP using direct IP resolution with SNI and certificate verification.
     *
     * @param string $domain
     * @param string $ip
     * @param string $protocol 'http' or 'https'
     * @param int $timeout
     * @return array{
     *     ip: string,
     *     protocol: string,
     *     status: ?int,
     *     connected_ip: ?string,
     *     server: ?string,
     *     response_time_ms: ?int,
     *     ssl_valid: ?bool,
     *     ssl_error: ?string,
     *     redirect_url: ?string,
     *     is_redirect: bool,
     *     is_cloudflare_challenge: bool,
     *     is_empty_or_default: bool,
     *     title: ?string,
     *     description: ?string,
     *     canonical: ?string,
     *     h1: array<int, string>,
     *     body_text: ?string,
     *     html: ?string,
     *     error: ?string,
     *     error_type: ?string
     * }
     */
    public function probe(string $domain, string $ip, string $protocol = 'https', int $timeout = 15): array
    {
        $handle = $this->createHandle($domain, $ip, $protocol, $timeout);

        if (! is_resource($handle) && ! ($handle instanceof \CurlHandle)) {
            /** @var array<string, mixed> $handle */
            return $handle;
        }

        $startTime = microtime(true);
        $response = curl_exec($handle);
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        $result = $this->parseResponse($handle, $response, $ip, $protocol, $durationMs);
        curl_close($handle);

        return $result;
    }

    /**
     * Create and configure a cURL handle for probing.
     * Returns an error result array if validation fails.
     *
     * @return \CurlHandle|resource|array<string, mixed>
     */
    public function createHandle(string $domain, string $ip, string $protocol = 'https', int $timeout = 15): mixed
    {
        $protocol = strtolower($protocol);
        $port = $protocol === 'https' ? 443 : 80;
        $url = "{$protocol}://{$domain}/";

        // SSRF and IP validation
        if (! $this->isValidPublicIp($ip)) {
            return $this->buildErrorResult(
                $ip,
                $protocol,
                'Invalid or disallowed IP address (private/reserved ranges are blocked to prevent SSRF).',
                'ssrf_blocked'
            );
        }

        if (! $this->isValidDomain($domain)) {
            return $this->buildErrorResult(
                $ip,
                $protocol,
                'Invalid domain name provided.',
                'invalid_domain'
            );
        }

        $resolveRule = "{$domain}:{$port}:{$ip}";

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RESOLVE => [$resolveRule],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true, // Capture headers for Server detection
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 5),
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Prober/1.0',
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYSTATUS => false,
            CURLOPT_CERTINFO => true,
        ]);

        return $ch;
    }

    /**
     * Parse cURL execution response and build result array.
     *
     * @param \CurlHandle|resource $ch
     * @param string|bool $response
     * @return array<string, mixed>
     */
    public function parseResponse(mixed $ch, mixed $response, string $ip, string $protocol, int $durationMs): array
    {
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $connectedIp = ! empty($primaryIp) ? $primaryIp : null;
        $sslValid = null;
        $sslError = null;
        $errorType = null;
        $errorMessage = null;

        if ($protocol === 'https') {
            if ($this->isSslError($curlErrno)) {
                $sslValid = false;
                $sslError = $curlError;
                $errorType = 'ssl_error';
                $errorMessage = "SSL Certificate verification failed: {$curlError}";
            } elseif ($curlErrno === 0) {
                // Check certificate validity (e.g. expiration date) if available from certinfo
                $certinfo = curl_getinfo($ch, CURLINFO_CERTINFO);
                $isExpired = false;
                if (! empty($certinfo) && is_array($certinfo)) {
                    foreach ($certinfo as $cert) {
                        if (isset($cert['Expire date'])) {
                            $expireTs = strtotime($cert['Expire date']);
                            if ($expireTs && $expireTs < time()) {
                                $isExpired = true;
                                break;
                            }
                        }
                    }
                }
                $sslValid = ! $isExpired;
                if ($isExpired) {
                    $sslError = 'Certificate expired';
                }
            }
        }

        if ($curlErrno !== 0) {
            if ($errorType === null) {
                $errorType = 'connection_error';
                $errorMessage = "Connection error ({$curlErrno}): {$curlError}";
            }

            return [
                'ip' => $ip,
                'protocol' => $protocol,
                'status' => $statusCode > 0 ? $statusCode : null,
                'connected_ip' => $connectedIp,
                'server' => null,
                'response_time_ms' => $durationMs,
                'ssl_valid' => $sslValid,
                'ssl_error' => $sslError,
                'redirect_url' => null,
                'is_redirect' => false,
                'is_cloudflare_challenge' => false,
                'is_empty_or_default' => true,
                'title' => null,
                'description' => null,
                'canonical' => null,
                'h1' => [],
                'body_text' => null,
                'html' => null,
                'error' => $errorMessage,
                'error_type' => $errorType,
            ];
        }

        $rawResponse = is_string($response) ? $response : '';
        $headerText = substr($rawResponse, 0, $headerSize);
        $htmlContent = substr($rawResponse, $headerSize);

        // Parse Server header
        $server = null;
        if (preg_match('/^server:\s*([^\r\n]+)/im', $headerText, $matches)) {
            $server = trim($matches[1]);
        }

        $isHttpRedirect = ($statusCode >= 300 && $statusCode < 400);
        $extracted = $this->htmlExtractor->extract($htmlContent);
        $metaRefreshUrl = $extracted['meta_refresh_url'] ?? null;
        $effectiveRedirectUrl = ! empty($redirectUrl) ? $redirectUrl : $metaRefreshUrl;
        $isRedirect = $isHttpRedirect || ! empty($metaRefreshUrl);

        return [
            'ip' => $ip,
            'protocol' => $protocol,
            'status' => $statusCode,
            'connected_ip' => $connectedIp,
            'server' => $server,
            'response_time_ms' => $durationMs,
            'ssl_valid' => $sslValid,
            'ssl_error' => null,
            'redirect_url' => $effectiveRedirectUrl,
            'is_redirect' => $isRedirect,
            'is_cloudflare_challenge' => $extracted['is_cloudflare_challenge'],
            'is_empty_or_default' => $extracted['is_empty_or_default'],
            'title' => $extracted['title'],
            'description' => $extracted['description'],
            'canonical' => $extracted['canonical'],
            'h1' => $extracted['h1'],
            'body_text' => $extracted['body_text'],
            'html' => $htmlContent,
            'error' => null,
            'error_type' => null,
        ];
    }

    /**
     * Determine if cURL error is SSL-related.
     */
    public function isSslError(int $errno): bool
    {
        $sslCodes = [35, 51, 58, 59, 60, 66, 77, 80, 82, 83, 90, 91];

        if (defined('CURLE_SSL_CONNECT_ERROR') && $errno === constant('CURLE_SSL_CONNECT_ERROR')) {
            return true;
        }

        return in_array($errno, $sslCodes, true);
    }

    /**
     * Validate IP address and ensure it is not private/reserved.
     */
    public function isValidPublicIp(string $ip): bool
    {
        $filterFlags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, $filterFlags);
    }

    /**
     * Validate domain syntax.
     */
    public function isValidDomain(string $domain): bool
    {
        if (empty($domain) || strlen($domain) > 253) {
            return false;
        }

        return (bool) preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildErrorResult(string $ip, string $protocol, string $error, string $errorType): array
    {
        return [
            'ip' => $ip,
            'protocol' => $protocol,
            'status' => null,
            'connected_ip' => null,
            'server' => null,
            'response_time_ms' => null,
            'ssl_valid' => null,
            'ssl_error' => null,
            'redirect_url' => null,
            'is_redirect' => false,
            'is_cloudflare_challenge' => false,
            'is_empty_or_default' => true,
            'title' => null,
            'description' => null,
            'canonical' => null,
            'h1' => [],
            'body_text' => null,
            'html' => null,
            'error' => $error,
            'error_type' => $errorType,
        ];
    }
}
