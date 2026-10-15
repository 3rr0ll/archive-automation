<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsoleController extends Controller
{
    public function __construct(
        protected GoogleSearchConsoleService $searchConsoleService
    ) {}

    /**
     * Run full registration: verify domain, submit sitemap, queue automatic indexing.
     */
    public function registerDomain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
            'sitemap_url' => ['nullable', 'url'],
        ]);

        $domain = $validated['domain'];
        $sitemapUrl = $validated['sitemap_url'] ?? '';

        try {
            $result = $this->searchConsoleService->registerDomain($domain, $sitemapUrl);

            return response()->json([
                'success' => true,
                'message' => 'Google Search Console registration initiated.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('GSC register domain API failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register domain with Google Search Console.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify domain property ownership via Cloudflare DNS TXT.
     */
    public function verifyProperty(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
        ]);

        $domain = $validated['domain'];

        try {
            $verified = $this->searchConsoleService->verifyProperty($domain);

            return response()->json([
                'success' => true,
                'message' => 'Domain property verified successfully.',
                'data' => [
                    'domain' => $domain,
                    'verified' => $verified,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('GSC verify property API failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify domain property with Google Search Console.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit sitemap for domain property.
     */
    public function submitSitemap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
            'sitemap_url' => ['nullable', 'url'],
        ]);

        $domain = $validated['domain'];
        $sitemapUrl = $validated['sitemap_url'] ?? '';

        try {
            $submitted = $this->searchConsoleService->submitSitemap($domain, $sitemapUrl);

            return response()->json([
                'success' => true,
                'message' => 'Sitemap submitted successfully.',
                'data' => [
                    'domain' => $domain,
                    'sitemap_url' => $sitemapUrl ?: "https://{$domain}/sitemap.xml",
                    'submitted' => $submitted,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('GSC submit sitemap API failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit sitemap to Google Search Console.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Request indexing for specific URLs directly via Indexing API.
     */
    public function requestIndexing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'urls' => ['required', 'array', 'min:1'],
            'urls.*' => ['required', 'url'],
        ]);

        $urls = $validated['urls'];

        try {
            $results = $this->searchConsoleService->requestIndexing($urls);

            return response()->json([
                'success' => true,
                'message' => 'Indexing request processed.',
                'data' => [
                    'results' => $results,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('GSC request indexing API failed', [
                'urls' => $urls,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to request indexing.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test fetching and parsing sitemap URLs.
     */
    public function testFetchSitemap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sitemap_url' => ['required', 'url'],
        ]);

        $sitemapUrl = $validated['sitemap_url'];

        try {
            $urls = $this->searchConsoleService->fetchSitemapUrls($sitemapUrl);

            return response()->json([
                'success' => true,
                'data' => [
                    'sitemap_url' => $sitemapUrl,
                    'total_urls' => count($urls),
                    'urls' => array_slice($urls, 0, 50), // Return preview of first 50
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sitemap.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
