<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnzipRemoteArchiveRequest;
use App\Models\Server;
use App\Services\InternetArchive\InternetArchiveService;
use App\Services\Sftp\SftpArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ArchiveController extends Controller
{
    public function __construct(
        protected InternetArchiveService $archiveService,
        private SftpArchiveService $sftpArchiveService,
    ) {}

    public function snapshots(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => ['required', 'string'],
        ]);

        $snapshots = $this->archiveService->getAvailableSnapshots($request->query('domain'));

        return response()->json([
            'data' => $snapshots,
        ]);
    }

    public function unzip(UnzipRemoteArchiveRequest $request, Server $server): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->sftpArchiveService->extract(
                $server->ip_address,
                $validated['archive_path'],
                $validated['destination_path'],
                (bool) ($validated['delete_archive'] ?? false),
                (bool) ($validated['delete_existing_index'] ?? true),
            );
        } catch (Throwable $exception) {
            Log::error('Remote archive extraction failed.', [
                'server_id' => $server->id,
                'runcloud_server_id' => $server->server_id,
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'The remote ZIP archive could not be extracted.',
            ], 502);
        }

        return response()->json([
            'message' => 'Remote ZIP archive extracted successfully.',
            'data' => $result,
        ]);
    }
}
