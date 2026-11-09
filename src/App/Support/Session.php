<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/** Encapsulates authenticated-user and CSRF session state. */
final class Session
{
    /** Returns the currently authenticated user, if one exists. */
    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** Returns the session CSRF token, creating it when necessary. */
    public function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
    }

    /** Verifies the CSRF token sent with a state-changing request. */
    public function checkCsrf(string $token): void
    {
        // Reject state-changing requests without the token rendered into the page.
        if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
            throw new RuntimeException('Your session token is invalid. Please refresh and try again.', 419);
        }
    }

    /** Stores authenticated user data in the session. */
    public function signIn(array $user): void
    {
        $_SESSION['user'] = $user;
    }

    /** Ends the current session. */
    public function signOut(): void
    {
        session_destroy();
    }
}
