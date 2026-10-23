<?php

namespace App\Services\Deployment;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ChunkedArchiveUploadService
{
    public function pruneExpired(User $user): void
    {
        $root = "deployment-uploads/{$user->getKey()}";
        $expiresBefore = now()->subDay()->getTimestamp();

        foreach (Storage::disk('local')->directories($root) as $directory) {
            $metadataPath = $directory.'/metadata.json';
            if (! Storage::disk('local')->exists($metadataPath)
                || Storage::disk('local')->lastModified($metadataPath) < $expiresBefore) {
                Storage::disk('local')->deleteDirectory($directory);
            }
        }
    }

    public function storeChunk(
        User $user,
        string $uploadId,
        UploadedFile $chunk,
        int $chunkIndex,
        int $totalChunks,
        string $originalName,
        int $totalSize,
    ): void {
        $directory = $this->directory($user, $uploadId);
        $metadata = [
            'original_name' => basename($originalName),
            'total_size' => $totalSize,
            'total_chunks' => $totalChunks,
        ];
        $metadataPath = $directory.'/metadata.json';

        if (Storage::disk('local')->exists($metadataPath)) {
            $existing = json_decode(Storage::disk('local')->get($metadataPath), true);
            if (! is_array($existing) || $existing !== $metadata) {
                throw new RuntimeException('The chunk metadata does not match the upload session.');
            }
        } else {
            Storage::disk('local')->put($metadataPath, json_encode($metadata, JSON_THROW_ON_ERROR));
        }

        $stored = $chunk->storeAs(
            $directory.'/chunks',
            sprintf('%06d.part', $chunkIndex),
            'local',
        );

        if (! is_string($stored)) {
            throw new RuntimeException('The archive chunk could not be stored.');
        }
    }

    /** @return array{upload_token: string, original_name: string, size: int} */
    public function complete(User $user, string $uploadId): array
    {
        $directory = $this->directory($user, $uploadId);
        $metadataPath = $directory.'/metadata.json';

        if (! Storage::disk('local')->exists($metadataPath)) {
            throw new RuntimeException('The upload session does not exist.');
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        if (! is_array($metadata)) {
            throw new RuntimeException('The upload session metadata is invalid.');
        }

        $assembledPath = $directory.'/archive.zip';
        if (Storage::disk('local')->exists($assembledPath)
            && Storage::disk('local')->size($assembledPath) === (int) $metadata['total_size']) {
            return [
                'upload_token' => $uploadId,
                'original_name' => (string) $metadata['original_name'],
                'size' => (int) $metadata['total_size'],
            ];
        }

        $output = fopen(Storage::disk('local')->path($assembledPath), 'wb');
        if ($output === false) {
            throw new RuntimeException('The archive could not be assembled.');
        }

        try {
            for ($index = 0; $index < (int) $metadata['total_chunks']; $index++) {
                $chunkPath = $directory.'/chunks/'.sprintf('%06d.part', $index);
                $input = Storage::disk('local')->readStream($chunkPath);
                if ($input === null || $input === false) {
                    throw new RuntimeException("Archive chunk {$index} is missing.");
                }

                try {
                    stream_copy_to_stream($input, $output);
                } finally {
                    fclose($input);
                }
            }
        } finally {
            fclose($output);
        }

        $actualSize = Storage::disk('local')->size($assembledPath);
        if ($actualSize !== (int) $metadata['total_size']) {
            Storage::disk('local')->delete($assembledPath);
            throw new RuntimeException('The assembled archive size does not match the selected file.');
        }

        $signature = file_get_contents(Storage::disk('local')->path($assembledPath), false, null, 0, 4);
        if (! is_string($signature) || ! str_starts_with($signature, "PK")) {
            Storage::disk('local')->delete($assembledPath);
            throw new RuntimeException('The uploaded file is not a valid ZIP archive.');
        }

        Storage::disk('local')->deleteDirectory($directory.'/chunks');

        return [
            'upload_token' => $uploadId,
            'original_name' => (string) $metadata['original_name'],
            'size' => $actualSize,
        ];
    }

    /** @return array{path: string, original_name: string} */
    public function copyToDeployment(User $user, string $uploadId, int $deploymentId): array
    {
        $directory = $this->directory($user, $uploadId);
        $metadataPath = $directory.'/metadata.json';
        $sourcePath = $directory.'/archive.zip';

        if (! Storage::disk('local')->exists($metadataPath)
            || ! Storage::disk('local')->exists($sourcePath)) {
            throw new RuntimeException('The completed archive upload could not be found. Upload it again.');
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $destination = "deployments/{$deploymentId}/archive.zip";

        if (! Storage::disk('local')->copy($sourcePath, $destination)) {
            throw new RuntimeException('The uploaded ZIP could not be saved for deployment.');
        }

        return [
            'path' => $destination,
            'original_name' => (string) ($metadata['original_name'] ?? 'archive.zip'),
        ];
    }

    public function forget(User $user, string $uploadId): void
    {
        Storage::disk('local')->deleteDirectory($this->directory($user, $uploadId));
    }

    private function directory(User $user, string $uploadId): string
    {
        return "deployment-uploads/{$user->getKey()}/{$uploadId}";
    }
}
