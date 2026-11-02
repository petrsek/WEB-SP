<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Food
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allWithRatings(): array
    {
        return $this->pdo->query(
            'SELECT f.*, COALESCE(ROUND(AVG(r.score), 1), 0) AS average_rating, COUNT(r.id) AS rating_count
             FROM foods f LEFT JOIN ratings r ON r.food_id = f.id GROUP BY f.id ORDER BY f.created_at DESC'
        )->fetchAll();
    }

    public function save(?int $id, string $name, string $description, string $category, float $price, ?string $imageUrl): void
    {
        if ($id !== null && $id > 0) {
            $statement = $this->pdo->prepare('UPDATE foods SET name=?, description=?, category=?, price=?, image_url=? WHERE id=?');
            $statement->execute([$name, $description, $category, $price, $imageUrl, $id]);
            return;
        }
        $statement = $this->pdo->prepare('INSERT INTO foods (name, description, category, price, image_url) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$name, $description, $category, $price, $imageUrl]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM foods WHERE id = ?');
        $statement->execute([$id]);
    }
}
