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
     * Run full registration: verify domain and submit its sitemap for discovery.
     */
    public function registerDomain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/i'],
            'sitemap_url' => ['nullable', 'url:http,https'],
        ]);

        $domain = $validated['domain'];
        $sitemapUrl = $validated['sitemap_url'] ?? '';

        try {
            $result = $this->searchConsoleService->registerDomain($domain, $sitemapUrl);

            return response()->json([
                'success' => true,
                'message' => 'Domain verified and sitemap submitted to Google Search Console.',
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
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/i'],
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
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/i'],
            'sitemap_url' => ['nullable', 'url:http,https'],
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

}
