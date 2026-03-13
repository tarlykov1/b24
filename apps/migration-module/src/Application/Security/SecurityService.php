<?php

declare(strict_types=1);

namespace MigrationModule\Application\Security;

use RuntimeException;

final class SecurityService
{
    public function encryptToken(string $token, string $key): string
    {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($token, 'aes-256-cbc', $this->normalizeKey($key), OPENSSL_RAW_DATA, $iv);

        if ($cipher === false) {
            throw new RuntimeException('Token encryption failed');
        }

        return base64_encode($iv . $cipher);
    }

    public function decryptToken(string $encryptedToken, string $key): string
    {
        $decoded = base64_decode($encryptedToken, true);
        if ($decoded === false || strlen($decoded) < 17) {
            throw new RuntimeException('Invalid encrypted token payload');
        }

        $iv = substr($decoded, 0, 16);
        $payload = substr($decoded, 16);
        $plain = openssl_decrypt($payload, 'aes-256-cbc', $this->normalizeKey($key), OPENSSL_RAW_DATA, $iv);

        if ($plain === false) {
            throw new RuntimeException('Token decryption failed');
        }

        return $plain;
    }

    public function maskToken(string $token): string
    {
        if (strlen($token) <= 6) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 3) . str_repeat('*', strlen($token) - 6) . substr($token, -3);
    }

    public function guardProductionRun(bool $allowProduction, bool $operatorConfirmed): void
    {
        if (!$allowProduction) {
            throw new RuntimeException('Production run blocked by safety guard');
        }

        if (!$operatorConfirmed) {
            throw new RuntimeException('Operator confirmation required before cutover');
        }
    }

    private function normalizeKey(string $key): string
    {
        return substr(hash('sha256', $key, true), 0, 32);
    }
}
