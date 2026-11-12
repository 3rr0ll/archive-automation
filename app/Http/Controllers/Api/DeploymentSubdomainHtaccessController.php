<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeploymentSubdomainHtaccessApplyRequest;
use App\Http\Requests\DeploymentSubdomainHtaccessPreviewRequest;
use App\Jobs\ConfigureRunCloudSubdomainSsl;
use App\Models\Deployment;
use App\Services\Deployment\SubdomainHtaccessService;
use App\Services\RunCloud\RunCloudWebAppService;
use App\Services\Sftp\SftpFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeploymentSubdomainHtaccessController extends Controller
{
    public function __construct(
        private SubdomainHtaccessService $subdomainHtaccessService,
        private RunCloudWebAppService $runCloudWebAppService,
        private SftpFileService $sftpFileService,
    ) {}

    /**
     * Resolve deployment target server IP and .htaccess file path.
     *
     * @return array{host: string, htaccess_path: string, web_app_name: string}
     */
    private function resolveTarget(Deployment $deployment): array
    {
        $server = $deployment->server;
        $host = $server?->ip_address;

        if (blank($host)) {
            abort(400, 'The deployment server IP address is not configured.');
        }

        $webAppName = $deployment->runcloud_web_app_name
            ?: $this->runCloudWebAppService->generateWebAppName($deployment->domain);

        if (! is_string($webAppName) || preg_match('/^[a-zA-Z0-9_-]+$/', $webAppName) !== 1) {
            abort(400, 'A valid RunCloud web application name could not be resolved.');
        }

        $webAppsRoot = rtrim((string) config('services.sftp.webapps_root', '/home/runcloud/webapps'), '/');
        $htaccessPath = "{$webAppsRoot}/{$webAppName}/public_html/.htaccess";

        return [
            'host' => $host,
            'htaccess_path' => $htaccessPath,
            'web_app_name' => $webAppName,
        ];
    }

    /**
     * Get .htaccess status and currently configured subdomains.
     */
    public function status(Deployment $deployment): JsonResponse
    {
        try {
            $target = $this->resolveTarget($deployment);
            $inspect = $this->subdomainHtaccessService->inspect($target['host'], $target['htaccess_path']);

            return response()->json([
                'success' => true,
                'data' => $inspect,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to inspect .htaccess for deployment', [
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'exists' => false,
                    'has_if_module' => false,
                    'configured_subdomains' => [],
                ],
            ], 400);
        }
    }

    /**
     * Preview rules before writing to .htaccess.
     */
    public function preview(DeploymentSubdomainHtaccessPreviewRequest $request, Deployment $deployment): JsonResponse
    {
        $validated = $request->validated();
        $existingContent = null;

        try {
            $target = $this->resolveTarget($deployment);
            if ($this->sftpFileService->fileExists($target['host'], $target['htaccess_path'])) {
                $existingContent = $this->sftpFileService->readFile($target['host'], $target['htaccess_path']);
            }
        } catch (Throwable) {
            // Proceed with null existing content if target could not be inspected
        }

        $preview = $this->subdomainHtaccessService->preview(
            $deployment->domain,
            $validated['folders'] ?? [],
            $existingContent,
        );

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }

    /**
     * Apply rules to .htaccess on the remote server.
     */
    public function apply(DeploymentSubdomainHtaccessApplyRequest $request, Deployment $deployment): JsonResponse
    {
        $validated = $request->validated();
        $target = $this->resolveTarget($deployment);

        try {
            $result = $this->subdomainHtaccessService->apply(
                $target['host'],
                $target['htaccess_path'],
                $deployment->domain,
                $validated['folders'] ?? [],
            );

            $deployment->logs()->create([
                'step_name' => 'configuring_subdomain_htaccess',
                'status' => 'success',
                'message' => $result['message'],
            ]);

            // Only run after .htaccess is written successfully.
            // Requires runcloud_web_app_id to be stored on the deployment —
            // we do NOT make a discovery API call here to avoid unnecessary RunCloud requests.
            $runCloudResult = null;
            if (! empty($validated['folders'])) {
                $serverId = (int) ($deployment->server?->server_id ?? 0);
                $appId = (int) $deployment->runcloud_web_app_id;

                if ($serverId && $appId) {
                    $runCloudResult = $this->runCloudWebAppService->attachSubdomainAliases(
                        $serverId,
                        $appId,
                        $deployment->domain,
                        $validated['folders'],
                    );

                    $runCloudSummary = sprintf(
                        'RunCloud subdomain aliases: %d added, %d skipped, %d failed.',
                        count($runCloudResult['added']),
                        count($runCloudResult['skipped']),
                        count($runCloudResult['failed']),
                    );

                    $deployment->logs()->create([
                        'step_name' => 'configuring_subdomain_runcloud',
                        'status' => count($runCloudResult['failed']) > 0 ? 'warning' : 'success',
                        'message' => $runCloudSummary,
                    ]);

                    // Existing aliases also need DNS/SSL repair. Keep certificate requests out of the HTTP request.
                    $queuedDomains = [];
                    foreach (['added', 'skipped'] as $group) {
                        foreach ($runCloudResult[$group] as &$alias) {
                            $alias['ssl'] = 'queued';
                            $subdomainName = $alias['subdomain'];
                            if (in_array($subdomainName, $queuedDomains, true)) {
                                continue;
                            }

                            $deployment->logs()->create([
                                'step_name' => 'configuring_subdomain_ssl',
                                'status' => 'in_progress',
                                'message' => "{$subdomainName}: Cloudflare DNS and SSL configuration queued.",
                            ]);

                            ConfigureRunCloudSubdomainSsl::dispatch($deployment, $subdomainName, $alias['id'] ?? null);
                            $queuedDomains[] = $subdomainName;
                        }
                        unset($alias);
                    }
                } else {
                    $runCloudResult = [
                        'added' => [],
                        'skipped' => [],
                        'failed' => [
                            [
                                'subdomain' => '',
                                'name' => '',
                                'error' => 'RunCloud web application ID or server ID is not stored on this deployment. Open the deployment and ensure it has been provisioned.',
                            ],
                        ],
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_merge($result, [
                    'runcloud' => $runCloudResult,
                ]),
                'message' => $result['message'],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to apply .htaccess subdomain routing', [
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
            ]);

            $deployment->logs()->create([
                'step_name' => 'configuring_subdomain_htaccess',
                'status' => 'error',
                'message' => 'Failed to apply subdomain rules: '.$e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
