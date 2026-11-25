<?php

declare(strict_types=1);

namespace App\Services\Prober;

class HtmlExtractorService
{
    /**
     * Parse HTML and extract metadata, headings, body text, and detection flags.
     *
     * @param string|null $html
     * @return array{
     *     title: ?string,
     *     description: ?string,
     *     canonical: ?string,
     *     h1: array<int, string>,
     *     body_text: string,
     *     is_empty_or_default: bool,
     *     is_cloudflare_challenge: bool
     * }
     */
    public function extract(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [
                'title' => null,
                'description' => null,
                'canonical' => null,
                'h1' => [],
                'body_text' => '',
                'is_empty_or_default' => true,
                'is_cloudflare_challenge' => false,
            ];
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // Load HTML with UTF-8 hint if possible
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);

        // Title
        $titleNode = $xpath->query('//title');
        $title = null;
        if ($titleNode && $titleNode->length > 0) {
            $title = trim($titleNode->item(0)->textContent);
        }

        // Description
        $metaDesc = $xpath->query('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]/@content');
        $description = null;
        if ($metaDesc && $metaDesc->length > 0) {
            $description = trim($metaDesc->item(0)->nodeValue ?? '');
        }

        // Canonical
        $canonicalNode = $xpath->query('//link[translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="canonical"]/@href');
        $canonical = null;
        if ($canonicalNode && $canonicalNode->length > 0) {
            $canonical = trim($canonicalNode->item(0)->nodeValue ?? '');
        }

        // H1 elements
        $h1Nodes = $xpath->query('//h1');
        $h1 = [];
        if ($h1Nodes) {
            foreach ($h1Nodes as $h1Node) {
                $text = trim($h1Node->textContent);
                if ($text !== '') {
                    $h1[] = preg_replace('/\s+/', ' ', $text);
                }
            }
        }

        // Extract body text while removing script, style, noscript, etc.
        $bodyNodes = $dom->getElementsByTagName('body');
        $bodyText = '';
        if ($bodyNodes->length > 0) {
            $body = $bodyNodes->item(0);
            $cleanDom = new \DOMDocument();
            $importedBody = $cleanDom->importNode($body, true);
            $cleanDom->appendChild($importedBody);

            $cleanXpath = new \DOMXPath($cleanDom);
            $removals = $cleanXpath->query('//script|//style|//noscript|//svg');
            if ($removals) {
                foreach ($removals as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }
            $rawText = $cleanDom->textContent;
            $bodyText = trim((string) preg_replace('/\s+/', ' ', $rawText));
        }

        $isCloudflareChallenge = $this->detectCloudflareChallenge($html, $title, $bodyText);
        $isEmptyOrDefault = $this->detectEmptyOrDefault($title, $bodyText, $html);
        $metaRefreshUrl = $this->extractMetaRefreshUrl($xpath, $html);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'h1' => $h1,
            'body_text' => $bodyText,
            'is_empty_or_default' => $isEmptyOrDefault,
            'is_cloudflare_challenge' => $isCloudflareChallenge,
            'meta_refresh_url' => $metaRefreshUrl,
        ];
    }

    /**
     * Extract redirect URL from <meta http-equiv="refresh" content="...;url=..."> or window.location
     */
    protected function extractMetaRefreshUrl(\DOMXPath $xpath, string $html): ?string
    {
        // 1. Check <meta http-equiv="refresh" content="...">
        $refreshNodes = $xpath->query('//meta[translate(@http-equiv, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="refresh"]/@content');
        if ($refreshNodes && $refreshNodes->length > 0) {
            $content = (string) $refreshNodes->item(0)->nodeValue;
            if (preg_match('/url\s*=\s*[\'"]?([^\'"\s>]+)/i', $content, $m)) {
                return trim($m[1]);
            }
        }

        // Regex fallback for malformed or unusual meta refresh
        if (preg_match('/<meta[^>]*http-equiv=[\'"]?refresh[\'"]?[^>]*content=[\'"]?[^;\'"]*;\s*url=([^\'"\s>]+)[\'"]?/i', $html, $m)) {
            return trim($m[1]);
        }

        // 2. Check JavaScript location redirect patterns (e.g. window.location.replace("..."), window.location.href = "...", window.location = "...")
        if (preg_match('/(?:window\.)?location(?:\.href|\.replace)?\s*=\s*[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
            return trim($m[1]);
        }

        // 3. Check plain text redirect hint (e.g. "This page should redirect you to http://...")
        if (preg_match('/redirect(?:ed)?\s+you\s+to\s+<a[^>]*href=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Detect Cloudflare turnstile / challenge pages.
     */
    protected function detectCloudflareChallenge(string $html, ?string $title, string $bodyText): bool
    {
        $haystack = strtolower(($title ?? '') . ' ' . $bodyText . ' ' . substr($html, 0, 5000));

        $patterns = [
            'just a moment...',
            'checking your browser',
            'attention required! | cloudflare',
            'enable javascript and cookies to continue',
            'challenges.cloudflare.com',
            'cf-browser-verification',
            'cf-chl-bypass',
            'cf-chl-opt',
            'turnstile',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect hosting default or placeholder pages.
     */
    protected function detectEmptyOrDefault(?string $title, string $bodyText, string $html): bool
    {
        if (trim($bodyText) === '' && strlen($html) < 200) {
            return true;
        }

        $haystack = strtolower(($title ?? '') . ' ' . $bodyText);

        $defaultIndicators = [
            'apache2 ubuntu default page',
            'welcome to nginx!',
            'test page for the apache http server',
            'iis windows server',
            'default web site page',
            'cpanel default web page',
            'plesk default page',
            'web server\'s default page',
            'domain default page',
        ];

        foreach ($defaultIndicators as $indicator) {
            if (str_contains($haystack, $indicator)) {
                return true;
            }
        }

        return false;
    }
}
