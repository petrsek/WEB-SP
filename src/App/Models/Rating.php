<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Rating
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT r.*, f.name AS food_name, u.name AS user_name FROM ratings r
             JOIN foods f ON f.id = r.food_id JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC'
        )->fetchAll();
    }

    public function save(int $foodId, int $userId, int $score, string $comment): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ratings (food_id, user_id, score, comment) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score = VALUES(score), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([$foodId, $userId, $score, $comment]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM ratings WHERE id = ?');
        $statement->execute([$id]);
    }
}
