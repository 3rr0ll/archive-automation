<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GoogleSearchConsoleSettingsController extends Controller
{
    public function __construct(
        private GoogleSearchConsoleSettings $settings,
        private GoogleSearchConsoleService $searchConsoleService,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->settings->status()]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);
        if ($validated['active'] && ! $this->settings->connected()) {
            return response()->json([
                'success' => false,
                'message' => 'Connect a Google account before enabling automation.',
            ], 422);
        }

        $this->settings->setActive((bool) $validated['active'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => $validated['active'] ? 'Google automation activated.' : 'Google automation deactivated.',
            'data' => $this->settings->status(),
        ]);
    }

    public function test(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Google Search Console connection succeeded.',
                'data' => $this->searchConsoleService->testConnection(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Google connection failed. Reconnect the account and confirm both APIs are enabled.',
            ], 502);
        }
    }

    public function disconnect(): JsonResponse
    {
        $this->settings->disconnect();

        return response()->json([
            'success' => true,
            'message' => 'Google Search Console disconnected.',
            'data' => $this->settings->status(),
        ]);
    }
}
