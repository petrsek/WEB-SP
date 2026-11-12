<?php
declare(strict_types=1);

namespace App\Infrastructure;

use PDO;

/** Owns the PDO connection, schema creation, and initial data setup. */
final class Database
{
    /** Configures the injected PDO connection and prepares application data. */
    public function __construct(private readonly PDO $pdo)
    {
        // Keep database failures visible instead of returning partial responses.
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureRatingVisibilityColumn();
    }

    /** Creates a database connection from the container environment. */
    public static function fromEnvironment(): self
    {
        $passwordPath = getenv('PASSWORD_FILE_PATH');
        $password = is_readable($passwordPath) ? trim((string) file_get_contents($passwordPath)) : '';
        $host = getenv('DB_HOST');
        $name = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $password);

        return new self($pdo);
    }

    /** Returns the configured PDO connection for model injection. */
    public function connection(): PDO
    {
        return $this->pdo;
    }

    /** Adds rating visibility to databases created before moderation controls existed. */
    private function ensureRatingVisibilityColumn(): void
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "ratings" AND COLUMN_NAME = "is_visible"'
        );
        $statement->execute();
        if ((int) $statement->fetchColumn() === 0) {
            $this->pdo->exec('ALTER TABLE ratings ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER comment');
        }
    }
}
