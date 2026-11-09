<?php
declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\HomeController;
use App\Infrastructure\Database;
use App\Models\Food;
use App\Models\Rating;
use App\Models\User;
use App\Support\Session;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/vendor/autoload.php';

session_start();

// Build shared services once and inject them into controllers and models.
$database = Database::fromEnvironment();
$session = new Session();
$userModel = new User($database->connection());
$foodModel = new Food($database->connection());
$ratingModel = new Rating($database->connection());

$apiController = new ApiController($session, $userModel, $foodModel, $ratingModel);
$action = (string) ($_GET['action'] ?? '');

if ($action !== '') {
    // Requests with an action are AJAX/API requests and must return JSON only.
    $apiController->handle($action);
    exit;
}

$twig = new Environment(new FilesystemLoader(__DIR__ . '/templates'));
$page = (string) ($_GET['page'] ?? 'index');
$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_GET['food_id'] ?? 0);
try {
    (new HomeController($twig, $session, $foodModel, $ratingModel, $userModel))->dispatch($page, $id);
} catch (RuntimeException $exception) {
    http_response_code($exception->getCode() >= 400 ? $exception->getCode() : 500);
    echo htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
}
