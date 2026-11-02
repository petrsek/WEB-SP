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

$database = Database::fromEnvironment();
$session = new Session();
$userModel = new User($database->connection());
$foodModel = new Food($database->connection());
$ratingModel = new Rating($database->connection());

$apiController = new ApiController($session, $userModel, $foodModel, $ratingModel);
$action = (string) ($_GET['action'] ?? '');

if ($action !== '') {
    $apiController->handle($action);
    exit;
}

$twig = new Environment(new FilesystemLoader(__DIR__ . '/templates'));
(new HomeController($twig, $session, $foodModel, $ratingModel, $userModel))->index();
