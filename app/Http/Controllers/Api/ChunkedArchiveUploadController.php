<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Deployment\ChunkedArchiveUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ChunkedArchiveUploadController extends Controller
{
    public function __construct(
        private ChunkedArchiveUploadService $uploads,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $maxBytes = (int) config('archive.max_upload_kb') * 1024;
        $chunkKilobytes = (int) config('archive.chunk_upload_kb');
        // Keep this independent from the configured chunk size so uploads that
        // started before a config-cache refresh can still finish safely.
        $maxChunks = (int) ceil($maxBytes / (256 * 1024)) + 1;
        $validated = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1', "max:{$maxChunks}"],
            'original_name' => ['required', 'string', 'max:255', 'regex:/\.zip$/i'],
            'total_size' => ['required', 'integer', 'min:1', "max:{$maxBytes}"],
            'chunk' => ['required', 'file', 'max:'.($chunkKilobytes + 64)],
        ]);

        if ((int) $validated['chunk_index'] >= (int) $validated['total_chunks']) {
            return response()->json(['message' => 'The archive chunk index is invalid.'], 422);
        }

        /** @var User $user */
        $user = $request->user();
        if ((int) $validated['chunk_index'] === 0) {
            $this->uploads->pruneExpired($user);
        }

        try {
            $this->uploads->storeChunk(
                $user,
                $validated['upload_id'],
                $request->file('chunk'),
                (int) $validated['chunk_index'],
                (int) $validated['total_chunks'],
                $validated['original_name'],
                (int) $validated['total_size'],
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Archive chunk uploaded.']);
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate(['upload_id' => ['required', 'uuid']]);

        try {
            /** @var User $user */
            $user = $request->user();
            $result = $this->uploads->complete($user, $validated['upload_id']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }
}
