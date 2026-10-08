<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInternetArchiveSettingsRequest;
use App\Services\InternetArchive\InternetArchiveService;
use App\Services\InternetArchive\InternetArchiveSettings;
use App\Services\InternetArchive\InternetArchiveSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternetArchiveSettingsController extends Controller
{
    public function show(InternetArchiveSettings $settings): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $settings->all(),
            'source' => $settings->hasDatabaseOverride() ? 'database' : 'environment',
        ]);
    }

    public function update(
        UpdateInternetArchiveSettingsRequest $request,
        InternetArchiveSettingsService $settingsService,
    ): JsonResponse {
        $settings = $settingsService->update($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Internet Archive settings updated successfully.',
            'data' => $settings,
            'source' => 'database',
        ]);
    }

    public function test(
        Request $request,
        InternetArchiveService $archiveService,
        InternetArchiveSettings $settings,
    ): JsonResponse {
        $configuredHosts = $settings->all()['allowed_hosts'];
        $defaultUrl = isset($configuredHosts[0]) ? 'https://'.$configuredHosts[0].'/' : null;
        $validated = $request->validate([
            'url' => [
                'nullable',
                'url:http,https',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) use ($archiveService): void {
                    if ($value !== null && ! $archiveService->isAllowedArchiveUrl((string) $value)) {
                        $fail('The url host is not included in the configured allowed_hosts setting.');
                    }
                },
            ],
        ]);
        $url = $validated['url'] ?? $defaultUrl;

        if ($url === null || ! $archiveService->testConnection($url)) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to the configured Internet Archive host.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Internet Archive connection succeeded.',
            'data' => ['url' => $url],
        ]);
    }
}
