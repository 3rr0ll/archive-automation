<?php

namespace App\Support;

class UrlSigner
{
    /**
     * Encode an integer ID into an encrypted URL-safe token.
     */
    public static function encode(int $id): string
    {
        $key = config('app.key') ?: 'archive-automation-default-key';
        $hash = substr(hash_hmac('sha256', (string) $id, $key), 0, 8);
        $payload = "{$id}:{$hash}";
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * Decode a URL-safe token back to an integer ID, returning null if invalid or tampered with.
     */
    public static function decode(string $token): ?int
    {
        $key = config('app.key') ?: 'archive-automation-default-key';
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }

        [$idStr, $hash] = explode(':', $decoded, 2);
        if (!ctype_digit($idStr)) {
            return null;
        }

        $expectedHash = substr(hash_hmac('sha256', $idStr, $key), 0, 8);
        if (!hash_equals($expectedHash, $hash)) {
            return null;
        }

        return (int) $idStr;
    }
}
