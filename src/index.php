<?php
declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/vendor/autoload.php';

session_start();

function envValue(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function database(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $passwordPath = envValue('PASSWORD_FILE_PATH', '/run/secrets/db-password');
    $password = is_readable($passwordPath) ? trim((string) file_get_contents($passwordPath)) : '';
    $host = envValue('DB_HOST', '127.0.0.1');
    $name = envValue('DB_NAME', 'web-sp');
    $user = envValue('DB_USER', 'root');
    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM("consumer", "manager", "admin", "superadmin") NOT NULL DEFAULT "consumer",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );
    $pdo->exec(
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
    $pdo->exec(
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

    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        foreach ([
            ['Super Admin', 'superadmin@flavorly.test', 'superadmin', 'superadmin'],
            ['Restaurant Admin', 'admin@flavorly.test', 'admin', 'admin'],
            ['Kitchen Manager', 'manager@flavorly.test', 'manager', 'manager'],
        ] as $account) {
            $insert->execute([$account[0], $account[1], password_hash($account[2], PASSWORD_DEFAULT), $account[3]]);
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM foods')->fetchColumn() === 0) {
        $insert = $pdo->prepare('INSERT INTO foods (name, description, category, price, image_url) VALUES (?, ?, ?, ?, ?)');
        foreach ([
            ['Truffle Mushroom Pasta', 'Creamy tagliatelle, wild mushrooms and shaved parmesan.', 'Pasta', 14.90, 'https://images.unsplash.com/photo-1473093295043-cdd812d0e601?w=800'],
            ['Crispy Chicken Burger', 'Buttermilk chicken, lettuce, pickles and house sauce.', 'Burgers', 12.50, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=800'],
            ['Garden Burrata Salad', 'Creamy burrata, heirloom tomatoes, basil oil and sourdough.', 'Salads', 10.90, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?w=800'],
        ] as $food) {
            $insert->execute($food);
        }
    }
    return $pdo;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireRole(array $roles): void
{
    $user = currentUser();
    if (!$user || !in_array($user['role'], $roles, true)) {
        respond(['error' => 'You are not allowed to perform this action.'], 403);
    }
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

function checkCsrf(): void
{
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) input('csrf'))) {
        respond(['error' => 'Your session token is invalid. Please refresh and try again.'], 419);
    }
}

$pdo = database();
$action = (string) ($_GET['action'] ?? '');

if ($action === 'login' || $action === 'register') {
    checkCsrf();
    $email = strtolower(trim((string) input('email')));
    $password = (string) input('password');
    if ($action === 'register') {
        $name = trim((string) input('name'));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            respond(['error' => 'Enter a name, a valid email and a password of at least 6 characters.'], 422);
        }
        try {
            $statement = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            if ((int) $exception->errorInfo[1] === 1062) {
                respond(['error' => 'An account with that email already exists.'], 422);
            }
            throw $exception;
        }
    }
    $statement = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ?');
    $statement->execute([$email]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        respond(['error' => 'The email or password is incorrect.'], 422);
    }
    unset($user['password']);
    $_SESSION['user'] = $user;
    respond(['ok' => true, 'redirect' => './']);
}

if ($action === 'logout') {
    checkCsrf();
    session_destroy();
    respond(['ok' => true, 'redirect' => './']);
}

if ($action !== '') {
    checkCsrf();
    $user = currentUser();
    if (!$user) {
        respond(['error' => 'Please sign in first.'], 401);
    }
    if ($action === 'rate') {
        requireRole(['consumer']);
        $score = (int) input('score');
        $foodId = (int) input('food_id');
        if ($score < 1 || $score > 5 || $foodId < 1) {
            respond(['error' => 'Choose a rating from 1 to 5.'], 422);
        }
        $statement = $pdo->prepare(
            'INSERT INTO ratings (food_id, user_id, score, comment) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score = VALUES(score), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([$foodId, (int) $user['id'], $score, trim((string) input('comment'))]);
        respond(['ok' => true, 'message' => 'Your rating was saved.']);
    }
    if ($action === 'food-save') {
        requireRole(['manager']);
        $foodId = (int) input('food_id');
        $values = [trim((string) input('name')), trim((string) input('description')), trim((string) input('category')), (float) input('price'), trim((string) input('image_url')) ?: null];
        if ($values[0] === '' || $values[1] === '' || $values[2] === '' || $values[3] <= 0) {
            respond(['error' => 'Name, description, category and a positive price are required.'], 422);
        }
        if ($foodId > 0) {
            $statement = $pdo->prepare('UPDATE foods SET name=?, description=?, category=?, price=?, image_url=? WHERE id=?');
            $statement->execute([...$values, $foodId]);
        } else {
            $statement = $pdo->prepare('INSERT INTO foods (name, description, category, price, image_url) VALUES (?, ?, ?, ?, ?)');
            $statement->execute($values);
        }
        respond(['ok' => true, 'message' => 'Menu updated.']);
    }
    if ($action === 'food-delete') {
        requireRole(['manager']);
        $statement = $pdo->prepare('DELETE FROM foods WHERE id = ?');
        $statement->execute([(int) input('food_id')]);
        respond(['ok' => true, 'message' => 'Food removed.']);
    }
    if ($action === 'rating-delete') {
        requireRole(['manager', 'admin']);
        $statement = $pdo->prepare('DELETE FROM ratings WHERE id = ?');
        $statement->execute([(int) input('rating_id')]);
        respond(['ok' => true, 'message' => 'Rating removed.']);
    }
    if ($action === 'user-role') {
        requireRole(['superadmin']);
        $role = (string) input('role');
        if (!in_array($role, ['consumer', 'manager', 'admin', 'superadmin'], true)) {
            respond(['error' => 'Invalid role.'], 422);
        }
        $statement = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $statement->execute([$role, (int) input('user_id')]);
        respond(['ok' => true, 'message' => 'User role updated.']);
    }
    respond(['error' => 'Unknown action.'], 404);
}

$foods = $pdo->query(
    'SELECT f.*, COALESCE(ROUND(AVG(r.score), 1), 0) AS average_rating, COUNT(r.id) AS rating_count
     FROM foods f LEFT JOIN ratings r ON r.food_id = f.id GROUP BY f.id ORDER BY f.created_at DESC'
)->fetchAll();
$ratings = $pdo->query(
    'SELECT r.*, f.name AS food_name, u.name AS user_name FROM ratings r
     JOIN foods f ON f.id = r.food_id JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC'
)->fetchAll();
$users = currentUser() && in_array(currentUser()['role'], ['admin', 'superadmin'], true)
    ? $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll()
    : [];

$twig = new Environment(new FilesystemLoader(__DIR__ . '/templates'));
echo $twig->render('home.twig', [
    'user' => currentUser(),
    'foods' => $foods,
    'ratings' => $ratings,
    'users' => $users,
    'csrf' => csrfToken(),
]);
