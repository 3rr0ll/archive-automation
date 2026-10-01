<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InternetArchive\InternetArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function __construct(
        protected InternetArchiveService $archiveService
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
}
