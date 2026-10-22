<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleSearchConsoleAccount;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleAccountService;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class GoogleSearchConsoleAccountController extends Controller
{
    public function __construct(
        private GoogleSearchConsoleAccountService $accounts,
        private GoogleSearchConsoleService $searchConsole,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->accounts->all()->map(fn (GoogleSearchConsoleAccount $account): array => $this->resource($account)),
            'legacy_fallback_configured' => $this->searchConsole->hasLegacyFallback(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules(requireCredentials: true));
        $validated['credentials'] = $this->decodeCredentials($validated['credentials_json']);
        unset($validated['credentials_json']);

        $account = $this->accounts->create($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Google service account added.',
            'data' => $this->resource($account),
        ], 201);
    }

    public function update(Request $request, GoogleSearchConsoleAccount $account): JsonResponse
    {
        $validated = $request->validate($this->rules(requireCredentials: false));
        if (filled($validated['credentials_json'] ?? null)) {
            $validated['credentials'] = $this->decodeCredentials($validated['credentials_json']);
        }
        unset($validated['credentials_json']);

        $account = $this->accounts->update($account, $validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Google service account updated.',
            'data' => $this->resource($account),
        ]);
    }

    public function activate(Request $request, GoogleSearchConsoleAccount $account): JsonResponse
    {
        $account = $this->accounts->activate($account, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Google service account activated.',
            'data' => $this->resource($account),
        ]);
    }

    public function test(GoogleSearchConsoleAccount $account): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Google Search Console connection succeeded.',
                'data' => $this->searchConsole->testAccount($account),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Google connection failed. Confirm the key and enable both Google APIs.',
            ], 502);
        }
    }

    public function destroy(GoogleSearchConsoleAccount $account): JsonResponse
    {
        $this->accounts->delete($account);

        return response()->json(['success' => true, 'message' => 'Google service account deleted.']);
    }

    /** @return array<string, array<int, string>> */
    private function rules(bool $requireCredentials): array
    {
        return [
            'name' => [$requireCredentials ? 'required' : 'sometimes', 'string', 'max:100'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'credentials_json' => [$requireCredentials ? 'required' : 'nullable', 'string', 'json'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function decodeCredentials(string $json): array
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['credentials_json' => ['Invalid JSON object.']]);
        }

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function resource(GoogleSearchConsoleAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'client_email' => $account->client_email,
            'owner_email' => $account->owner_email,
            'is_active' => $account->is_active,
            'type' => $account->credentials['type'] ?? 'service_account',
            'created_at' => $account->created_at?->toISOString(),
            'updated_at' => $account->updated_at?->toISOString(),
        ];
    }
}
