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
        $this->createSchema();
        $this->seed();
    }

    /** Creates a database connection from the container environment. */
    public static function fromEnvironment(): self
    {
        $passwordPath = getenv('PASSWORD_FILE_PATH') ?: '/run/secrets/db-password';
        $password = is_readable($passwordPath) ? trim((string) file_get_contents($passwordPath)) : '';
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'web-sp';
        $user = getenv('DB_USER') ?: 'root';
        $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $password);

        return new self($pdo);
    }

    /** Returns the configured PDO connection for model injection. */
    public function connection(): PDO
    {
        return $this->pdo;
    }

    /** Creates the tables required by the application when they do not exist. */
    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(190) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role ENUM("consumer", "manager", "admin", "superadmin") NOT NULL DEFAULT "consumer",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS foods (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                description TEXT NOT NULL,
                category VARCHAR(80) NOT NULL,
                price DECIMAL(8,2) NOT NULL,
                image_url VARCHAR(500) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ratings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                food_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                score TINYINT UNSIGNED NOT NULL,
                comment VARCHAR(500) NOT NULL DEFAULT "",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY one_rating_per_consumer (food_id, user_id),
                CONSTRAINT ratings_food FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE CASCADE,
                CONSTRAINT ratings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT score_range CHECK (score BETWEEN 1 AND 5)
            ) ENGINE=InnoDB'
        );
    }

    /** Adds initial demo users and menu items to an empty database. */
    private function seed(): void
    {
        // Demo records are inserted only into an empty database.
        if ((int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
            $insert = $this->pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            foreach ([
                ['Super Admin', 'superadmin@flavourtown.test', 'superadmin', 'superadmin'],
                ['Restaurant Admin', 'admin@flavourtown.test', 'admin', 'admin'],
                ['Kitchen Manager', 'manager@flavourtown.test', 'manager', 'manager'],
            ] as $account) {
                $insert->execute([$account[0], $account[1], password_hash($account[2], PASSWORD_DEFAULT), $account[3]]);
            }
        }
        if ((int) $this->pdo->query('SELECT COUNT(*) FROM foods')->fetchColumn() === 0) {
            $insert = $this->pdo->prepare('INSERT INTO foods (name, description, category, price, image_url) VALUES (?, ?, ?, ?, ?)');
            foreach ([
                ['Truffle Mushroom Pasta', 'Creamy tagliatelle, wild mushrooms and shaved parmesan.', 'Pasta', 14.90, 'https://images.unsplash.com/photo-1473093295043-cdd812d0e601?w=800'],
                ['Crispy Chicken Burger', 'Buttermilk chicken, lettuce, pickles and house sauce.', 'Burgers', 12.50, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=800'],
                ['Garden Burrata Salad', 'Creamy burrata, heirloom tomatoes, basil oil and sourdough.', 'Salads', 10.90, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?w=800'],
            ] as $food) {
                $insert->execute($food);
            }
        }
    }
}
