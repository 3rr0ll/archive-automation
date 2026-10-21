<?php

namespace App\Services\GoogleSearchConsole;

use Google\Client as GoogleClient;
use Google\Service\SearchConsole;
use Google\Service\SiteVerification;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use RuntimeException;

class GoogleSearchConsoleOAuthService
{
    public function __construct(
        private GoogleSearchConsoleSettings $settings,
    ) {}

    public function authorizationUrl(Request $request): string
    {
        $client = $this->baseClient();
        $state = bin2hex(random_bytes(32));
        $request->session()->put('google_search_console_oauth_state', $state);
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * @return array{refresh_token: string, email: string}
     */
    public function exchangeCode(Request $request): array
    {
        $expectedState = (string) $request->session()->pull('google_search_console_oauth_state', '');
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $receivedState)) {
            throw new RuntimeException('The Google authorization state is invalid or expired.');
        }

        if ($request->filled('error')) {
            throw new RuntimeException('Google authorization was not completed.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            throw new RuntimeException('Google did not return an authorization code.');
        }

        $client = $this->baseClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new RuntimeException('Google rejected the authorization code.');
        }

        $refreshToken = (string) ($token['refresh_token'] ?? '');
        if ($refreshToken === '') {
            throw new RuntimeException('Google did not return offline access. Reconnect and approve access again.');
        }

        $identity = isset($token['id_token']) ? $client->verifyIdToken($token['id_token']) : false;
        $email = is_array($identity) ? (string) ($identity['email'] ?? '') : '';
        if ($email === '') {
            throw new RuntimeException('Google did not return the connected account email.');
        }

        return ['refresh_token' => $refreshToken, 'email' => $email];
    }

    public function authorizedClient(): GoogleClient
    {
        $refreshToken = $this->settings->refreshToken();
        if ($refreshToken === null) {
            throw new RuntimeException('Google Search Console is not connected.');
        }

        $client = $this->baseClient();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (isset($token['error'])) {
            throw new RuntimeException('The Google authorization has expired or was revoked. Reconnect the account.');
        }

        return $client;
    }

    private function baseClient(): GoogleClient
    {
        if (! $this->settings->clientConfigured()) {
            throw new RuntimeException('GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI must be configured.');
        }

        $client = new GoogleClient;
        $client->setApplicationName((string) config('app.name'));
        $client->setClientId($this->settings->clientId());
        $client->setClientSecret($this->settings->clientSecret());
        $client->setRedirectUri($this->settings->redirectUri());
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setScopes([
            'openid',
            'email',
            SiteVerification::SITEVERIFICATION,
            SearchConsole::WEBMASTERS,
        ]);
        $client->setHttpClient(new Client([
            'timeout' => (int) config('services.google_search_console.timeout', 30),
        ]));

        return $client;
    }
}
