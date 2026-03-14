<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Http;

final class AdminAuth
{
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'secure' => !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true),
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public function login(string $password): bool
    {
        $this->startSession();
        $hash = (string) ($_ENV['MIGRATION_ADMIN_PASSWORD_HASH'] ?? '');
        if ($hash === '') {
            return false;
        }

        if (!password_verify($password, $hash)) {
            return false;
        }

        $_SESSION['migration_admin_auth'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));

        return true;
    }

    public function logout(): void
    {
        $this->startSession();
        $_SESSION = [];
        session_destroy();
    }

    public function requireAuth(): void
    {
        $this->startSession();
        if (($_SESSION['migration_admin_auth'] ?? false) !== true) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'unauthorized']);
            exit;
        }
    }

    public function csrfToken(): string
    {
        $this->startSession();

        return (string) ($_SESSION['csrf'] ?? '');
    }

    public function validateCsrf(?string $token): bool
    {
        $known = $this->csrfToken();

        return $known !== '' && is_string($token) && hash_equals($known, $token);
    }
}
