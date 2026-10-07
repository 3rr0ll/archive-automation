<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInternetArchiveSettingsRequest;
use App\Models\ApiCredential;
use App\Models\User;
use App\Services\InternetArchive\InternetArchiveService;
use App\Services\InternetArchive\InternetArchiveSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        InternetArchiveSettings $settings,
    ): JsonResponse {
        $validated = $request->validated();
        $payload = [
            'allowed_hosts' => array_values(array_map(
                fn (string $host): string => strtolower(trim($host)),
                $validated['allowed_hosts'],
            )),
            'timeout' => $validated['timeout'],
            'poll_delay' => $validated['poll_delay'],
            'max_attempts' => $validated['max_attempts'],
        ];

        DB::transaction(function () use ($request, $payload): void {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            $credential = ApiCredential::query()
                ->where('service', 'internet_archive')
                ->latest('updated_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            $attributes = [
                'user_id' => $request->user()->id,
                'account_name' => 'Global Internet Archive settings',
                'token_data_encrypted' => json_encode($payload, JSON_THROW_ON_ERROR),
            ];

            if ($credential === null) {
                $credential = ApiCredential::create([
                    ...$attributes,
                    'service' => 'internet_archive',
                ]);
            } else {
                $credential->update($attributes);
            }

            ApiCredential::query()
                ->where('service', 'internet_archive')
                ->whereKeyNot($credential->getKey())
                ->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Internet Archive settings updated successfully.',
            'data' => $settings->all(),
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
