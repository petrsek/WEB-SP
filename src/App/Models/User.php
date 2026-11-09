<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/** Provides persistence operations for application users. */
final class User
{
    /** Stores the PDO dependency used for user queries. */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Creates a consumer account and returns its authenticated user data. */
    public function register(string $name, string $email, string $password): array
    {
        try {
            // The unique email constraint is converted into a user-facing error.
            $statement = $this->pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new RuntimeException('An account with that email already exists.', 422, $exception);
            }
            throw $exception;
        }

        return $this->authenticate($email, $password);
    }

    /** Validates credentials and returns a user without the password hash. */
    public function authenticate(string $email, string $password): array
    {
        $statement = $this->pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            throw new RuntimeException('The email or password is incorrect.', 422);
        }
        unset($user['password']);

        return $user;
    }

    /** Returns all users for administrator management screens. */
    public function all(): array
    {
        return $this->pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
    }

    /** Changes the role assigned to a user. */
    public function updateRole(int $id, string $role): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $statement->execute([$role, $id]);
    }
}
