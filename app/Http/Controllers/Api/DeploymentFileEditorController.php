<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Services\RunCloud\RunCloudWebAppService;
use App\Services\Sftp\SftpFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeploymentFileEditorController extends Controller
{
    public function __construct(
        private SftpFileService $sftpFileService,
        private RunCloudWebAppService $runCloudWebAppService,
    ) {}

    /**
     * Resolve the remote server IP and webapp root directory.
     *
     * @return array{host: string, root_dir: string, web_app_name: string}
     */
    private function resolveDeploymentTarget(Deployment $deployment): array
    {
        $server = $deployment->server;
        $host = $server?->ip_address;

        if (blank($host)) {
            abort(400, 'The deployment server IP address is not configured.');
        }

        $webAppName = $this->runCloudWebAppService->generateWebAppName($deployment->domain);
        $webAppsRoot = rtrim((string) config('services.sftp.webapps_root', '/home/runcloud/webapps'), '/');
        $rootDir = "{$webAppsRoot}/{$webAppName}/public_html";

        return [
            'host' => $host,
            'root_dir' => $rootDir,
            'web_app_name' => $webAppName,
        ];
    }

    /**
     * Get directory listing.
     */
    public function listDirectory(Request $request, Deployment $deployment): JsonResponse
    {
        $target = $this->resolveDeploymentTarget($deployment);

        $requestedPath = (string) $request->query('path', '');
        $remoteDir = filled($requestedPath) ? $requestedPath : $target['root_dir'];

        // Normalize
        $remoteDir = str_replace('\\', '/', $remoteDir);
        if (! str_starts_with($remoteDir, $target['root_dir'])) {
            $remoteDir = $target['root_dir'];
        }

        try {
            $items = $this->sftpFileService->listDirectory($target['host'], $remoteDir);

            return response()->json([
                'success' => true,
                'data' => [
                    'root_dir' => $target['root_dir'],
                    'current_dir' => $remoteDir,
                    'items' => $items,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('SFTP file tree list failed: ' . $e->getMessage(), [
                'deployment_id' => $deployment->id,
                'path' => $remoteDir,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Read file content.
     */
    public function readFile(Request $request, Deployment $deployment): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
        ]);

        $target = $this->resolveDeploymentTarget($deployment);
        $filePath = str_replace('\\', '/', $request->input('path'));

        if (! str_starts_with($filePath, $target['root_dir'])) {
            return response()->json([
                'success' => false,
                'message' => 'Path is outside webapp directory.',
            ], 403);
        }

        try {
            $content = $this->sftpFileService->readFile($target['host'], $filePath);

            return response()->json([
                'success' => true,
                'data' => [
                    'path' => $filePath,
                    'filename' => basename($filePath),
                    'content' => $content,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('SFTP file read failed: ' . $e->getMessage(), [
                'deployment_id' => $deployment->id,
                'path' => $filePath,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save/Write file content.
     */
    public function writeFile(Request $request, Deployment $deployment): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
            'content' => ['present', 'string'],
        ]);

        $target = $this->resolveDeploymentTarget($deployment);
        $filePath = str_replace('\\', '/', $request->input('path'));
        $content = (string) $request->input('content');

        if (! str_starts_with($filePath, $target['root_dir'])) {
            return response()->json([
                'success' => false,
                'message' => 'Path is outside webapp directory.',
            ], 403);
        }

        try {
            $this->sftpFileService->writeFile($target['host'], $filePath, $content);

            return response()->json([
                'success' => true,
                'message' => 'File saved successfully.',
                'data' => [
                    'path' => $filePath,
                    'size' => strlen($content),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('SFTP file save failed: ' . $e->getMessage(), [
                'deployment_id' => $deployment->id,
                'path' => $filePath,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new empty file.
     */
    public function createFile(Request $request, Deployment $deployment): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
        ]);

        $target = $this->resolveDeploymentTarget($deployment);
        $filePath = str_replace('\\', '/', $request->input('path'));

        if (! str_starts_with($filePath, $target['root_dir'])) {
            return response()->json([
                'success' => false,
                'message' => 'Path is outside webapp directory.',
            ], 403);
        }

        try {
            $this->sftpFileService->writeFile($target['host'], $filePath, '');

            return response()->json([
                'success' => true,
                'message' => 'File created successfully.',
                'data' => [
                    'path' => $filePath,
                    'name' => basename($filePath),
                    'type' => 'file',
                    'size' => 0,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('SFTP file create failed: ' . $e->getMessage(), [
                'deployment_id' => $deployment->id,
                'path' => $filePath,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
