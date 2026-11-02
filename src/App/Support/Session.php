<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Session
{
    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
    }

    public function checkCsrf(string $token): void
    {
        if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
            throw new RuntimeException('Your session token is invalid. Please refresh and try again.', 419);
        }
    }

    public function signIn(array $user): void
    {
        $_SESSION['user'] = $user;
    }

    public function signOut(): void
    {
        session_destroy();
    }
}
