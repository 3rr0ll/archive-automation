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
     * Run full registration: verify/add domain, submit sitemap, and optionally request indexing.
     */
    public function registerDomain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/i'],
            'sitemap_url' => ['nullable', 'url:http,https'],
            'verify_ownership' => ['nullable', 'boolean'],
            'request_indexing' => ['nullable', 'boolean'],
        ]);

        $domain = $validated['domain'];
        $sitemapUrl = $validated['sitemap_url'] ?? '';
        $verifyOwnership = $validated['verify_ownership'] ?? true;
        $requestIndexing = $validated['request_indexing'] ?? true;

        try {
            $result = $this->searchConsoleService->registerDomain(
                $domain,
                $sitemapUrl,
                $verifyOwnership,
                $requestIndexing,
            );

            return response()->json([
                'success' => true,
                'message' => 'Domain registered and submitted to Google Search Console.',
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
     * Add a site property directly to Google Search Console without requiring DNS verification.
     */
    public function addProperty(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property' => ['required', 'string', 'max:253'],
        ]);

        $property = $validated['property'];

        try {
            $result = $this->searchConsoleService->addProperty($property);

            return response()->json([
                'success' => true,
                'message' => 'Property added to Google Search Console.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('GSC add property API failed', [
                'property' => $property,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add property to Google Search Console.',
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
            'domain' => ['required', 'string', 'max:253'],
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

    /**
     * Request indexing for a specific URL using the Google Indexing API.
     */
    public function requestIndexing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url:http,https'],
            'type' => ['nullable', 'string', 'in:URL_UPDATED,URL_DELETED'],
        ]);

        $url = $validated['url'];
        $type = $validated['type'] ?? 'URL_UPDATED';

        try {
            $result = $this->searchConsoleService->requestIndexing($url, $type);

            return response()->json([
                'success' => true,
                'message' => 'Indexing request published to Google.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('Google Indexing API failed', [
                'url' => $url,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to request URL indexing with Google.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check indexing notification metadata for a URL.
     */
    public function getIndexingStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url:http,https'],
        ]);

        $url = $validated['url'];

        try {
            $result = $this->searchConsoleService->getIndexingStatus($url);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get indexing status from Google.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Google OAuth consent URL.
     */
    public function getOAuthUrl(Request $request): JsonResponse
    {
        try {
            $state = $request->query('state');
            $authUrl = $this->searchConsoleService->createOAuthUrl(is_string($state) ? $state : null);

            return response()->json([
                'success' => true,
                'auth_url' => $authUrl,
                'redirect_uri' => config('services.google_search_console.redirect_uri'),
                'client_id' => config('services.google_search_console.client_id'),
                'scopes' => [
                    'https://www.googleapis.com/auth/webmasters',
                    'https://www.googleapis.com/auth/siteverification',
                    'https://www.googleapis.com/auth/indexing',
                    'openid',
                    'email',
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Exchange an authorization code or refresh token for stored OAuth credentials.
     */
    public function exchangeOAuthCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'required_without:refresh_token'],
            'refresh_token' => ['nullable', 'string', 'required_without:code'],
            'email' => ['nullable', 'email'],
        ]);

        try {
            if (! empty($validated['code'])) {
                $account = $this->searchConsoleService->handleOAuthCallback(
                    $validated['code'],
                    $request->user()?->id,
                );
            } else {
                $account = $this->searchConsoleService->handleRefreshToken(
                    $validated['refresh_token'],
                    $validated['email'] ?? null,
                    $request->user()?->id,
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Google account linked and activated successfully.',
                'data' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'client_email' => $account->client_email,
                    'is_active' => $account->is_active,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('OAuth token exchange failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to exchange authorization code: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Check Google Search Console connection & OAuth status.
     */
    public function oauthStatus(Request $request): JsonResponse
    {
        $activeAccount = app(\App\Services\GoogleSearchConsole\GoogleSearchConsoleAccountService::class)->active();
        $accountType = null;

        if ($activeAccount) {
            $accountType = $activeAccount->credentials['type'] ?? 'service_account';
        } elseif ($this->searchConsoleService->hasOAuthEnvFallback()) {
            $accountType = 'oauth_env';
        } elseif ($this->searchConsoleService->hasLegacyFallback()) {
            $accountType = 'service_account_file';
        }

        return response()->json([
            'success' => true,
            'is_enabled' => $this->searchConsoleService->isEnabled(),
            'oauth_configured' => $this->searchConsoleService->hasOAuthConfiguration(),
            'client_id' => config('services.google_search_console.client_id'),
            'redirect_uri' => config('services.google_search_console.redirect_uri'),
            'timeout' => (int) config('services.google_search_console.timeout', 30),
            'active_account' => $activeAccount ? [
                'id' => $activeAccount->id,
                'name' => $activeAccount->name,
                'client_email' => $activeAccount->client_email,
                'type' => $accountType,
            ] : null,
            'account_type' => $accountType,
        ]);
    }

}
