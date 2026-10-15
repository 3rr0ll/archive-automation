<?php

namespace App\Services\Sftp;

use App\Models\EnvironmentVariable;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use RuntimeException;
use Throwable;

class SftpConnectionFactory
{
    public function connect(string $host): SFTP
    {
        $username = (string) $this->getEnvironmentValue(
            'SFTP_USERNAME',
            config('services.sftp.username'),
        );

        if ($username === '') {
            throw new RuntimeException('SFTP credentials are not configured.');
        }

        $client = new SFTP(
            $host,
            (int) config('services.sftp.port', 22),
            (int) config('services.sftp.timeout', 30),
        );

        $this->verifyHostKey($client);

        if (! $client->login($username, $this->credential())) {
            throw new RuntimeException('SFTP authentication failed.');
        }

        return $client;
    }

    private function credential(): string|PrivateKey
    {
        $privateKey = $this->privateKey();

        if ($privateKey !== null) {
            try {
                return PublicKeyLoader::loadPrivateKey(
                    $privateKey,
                    config('services.sftp.private_key_passphrase'),
                );
            } catch (Throwable $exception) {
                throw new RuntimeException('The configured SFTP private key could not be loaded.', 0, $exception);
            }
        }

        $password = $this->getEnvironmentValue(
            'SFTP_PASSWORD',
            config('services.sftp.password'),
        );

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('SFTP credentials are not configured. Add SFTP_PASSWORD in the Environment Variables UI or .env file.');
        }

        return $password;
    }

    /**
     * Read an encrypted database environment variable before falling back to config/.env.
     */
    private function getEnvironmentValue(string $key, mixed $fallback = null): mixed
    {
        try {
            $variable = EnvironmentVariable::query()
                ->where('key', $key)
                ->latest('updated_at')
                ->latest('id')
                ->first();

            if ($variable !== null && filled($variable->value)) {
                return $variable->value;
            }
        } catch (Throwable) {
            // The database may be unavailable before migrations or during bootstrap.
        }

        return $fallback;
    }

    private function privateKey(): ?string
    {
        $inlineKey = config('services.sftp.private_key');

        if (is_string($inlineKey) && $inlineKey !== '') {
            return str_replace('\\n', "\n", $inlineKey);
        }

        $path = config('services.sftp.private_key_path');

        if (! is_string($path) || $path === '') {
            return null;
        }

        $contents = @file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException('The configured SFTP private key file could not be read.');
        }

        return $contents;
    }

    private function verifyHostKey(SFTP $client): void
    {
        $expectedFingerprint = config('services.sftp.host_fingerprint');

        if (! is_string($expectedFingerprint) || $expectedFingerprint === '') {
            return;
        }

        $hostKey = $client->getServerPublicHostKey();

        if (! is_string($hostKey)) {
            throw new RuntimeException('The SFTP server host key could not be read.');
        }

        $keyParts = explode(' ', $hostKey, 3);
        $keyBytes = isset($keyParts[1]) ? base64_decode($keyParts[1], true) : false;

        if (! is_string($keyBytes)) {
            throw new RuntimeException('The SFTP server returned an invalid host key.');
        }

        $actualFingerprint = 'SHA256:'.rtrim(base64_encode(hash('sha256', $keyBytes, true)), '=');

        if (! hash_equals($expectedFingerprint, $actualFingerprint)) {
            throw new RuntimeException('The SFTP server host key does not match the configured fingerprint.');
        }
    }
}
