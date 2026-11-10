<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/** Provides persistence operations for food ratings. */
final class Rating
{
    /** Stores the PDO dependency used for rating queries. */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Returns ratings together with their food and author names. */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT r.*, f.name AS food_name, u.name AS user_name FROM ratings r
             JOIN foods f ON f.id = r.food_id JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC'
        )->fetchAll();
    }

    /** Returns all ratings belonging to one food item. */
    public function forFood(int $foodId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.*, u.name AS user_name FROM ratings r JOIN users u ON u.id = r.user_id
             WHERE r.food_id = ? ORDER BY r.created_at DESC'
        );
        $statement->execute([$foodId]);
        return $statement->fetchAll();
    }

    /** Returns all ratings authored by one user, including their food names. */
    public function forUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.*, f.name AS food_name, u.name AS user_name FROM ratings r
             JOIN foods f ON f.id = r.food_id JOIN users u ON u.id = r.user_id
             WHERE r.user_id = ? ORDER BY r.created_at DESC'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    /** Returns the current consumer's rating for a food item, if it exists. */
    public function forUserAndFood(int $userId, int $foodId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ratings WHERE user_id = ? AND food_id = ?');
        $statement->execute([$userId, $foodId]);
        return $statement->fetch() ?: null;
    }

    /** Creates a rating or updates the consumer's existing rating. */
    public function save(int $foodId, int $userId, int $score, string $comment): void
    {
        // The unique key lets consumers edit their existing rating.
        $statement = $this->pdo->prepare(
            'INSERT INTO ratings (food_id, user_id, score, comment) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score = VALUES(score), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([$foodId, $userId, $score, $comment]);
    }

    /** Removes a rating by its database ID. */
    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM ratings WHERE id = ?');
        $statement->execute([$id]);
    }
}
