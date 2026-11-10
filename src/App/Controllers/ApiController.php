<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Food;
use App\Models\Rating;
use App\Models\User;
use App\Support\Session;
use RuntimeException;
use Throwable;

/** Handles AJAX commands and applies request-level authorization. */
final class ApiController
{
    /** Injects services used by all AJAX actions. */
    public function __construct(
        private readonly Session $session,
        private readonly User $users,
        private readonly Food $foods,
        private readonly Rating $ratings
    ) {
    }

    /** Dispatches an action and returns a JSON success or error response. */
    public function handle(string $action): void
    {
        try {
            // All mutations share the same CSRF and JSON error handling.
            $this->session->checkCsrf((string) $this->input('csrf'));
            $result = match ($action) {
                'login' => $this->login(),
                'register' => $this->register(),
                'logout' => $this->logout(),
                'rate' => $this->rate(),
                'food-save' => $this->saveFood(),
                'food-delete' => $this->deleteFood(),
                'rating-delete' => $this->deleteRating(),
                'user-role' => $this->updateRole(),
                default => throw new RuntimeException('Unknown action.', 404),
            };
            $this->respond($result);
        } catch (Throwable $exception) {
            $status = $exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 500;
            $this->respond(['error' => $exception->getMessage()], $status);
        }
    }

    /** Authenticates an existing user. */
    private function login(): array
    {
        $user = $this->users->authenticate($this->email(), (string) $this->input('password'));
        $this->session->signIn($user);
        return ['ok' => true, 'redirect' => './'];
    }

    /** Validates and creates a new consumer account. */
    private function register(): array
    {
        $name = trim((string) $this->input('name'));
        $email = $this->email();
        $password = (string) $this->input('password');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            throw new RuntimeException('Enter a name, a valid email and a password of at least 6 characters.', 422);
        }
        $this->session->signIn($this->users->register($name, $email, $password));
        return ['ok' => true, 'redirect' => './'];
    }

    /** Clears the authenticated session. */
    private function logout(): array
    {
        $this->session->signOut();
        return ['ok' => true, 'redirect' => './'];
    }

    /** Saves a consumer rating for a food item. */
    private function rate(): array
    {
        $this->requireRole(['consumer', 'superadmin']);
        $score = (int) $this->input('score');
        if ($score < 1 || $score > 5 || (int) $this->input('food_id') < 1) {
            throw new RuntimeException('Choose a rating from 1 to 5.', 422);
        }
        $this->ratings->save((int) $this->input('food_id'), (int) $this->session->user()['id'], $score, trim((string) $this->input('comment')));
        return ['ok' => true, 'message' => 'Your rating was saved.'];
    }

    /** Validates and saves a manager's food item changes. */
    private function saveFood(): array
    {
        $this->requireRole(['manager', 'superadmin']);
        $values = [trim((string) $this->input('name')), trim((string) $this->input('description')), trim((string) $this->input('category')), (float) $this->input('price'), trim((string) $this->input('image_url')) ?: null];
        if ($values[0] === '' || $values[1] === '' || $values[2] === '' || $values[3] <= 0) {
            throw new RuntimeException('Name, description, category and a positive price are required.', 422);
        }
        $this->foods->save((int) $this->input('food_id') ?: null, ...$values);
        return ['ok' => true, 'message' => 'Menu updated.'];
    }

    /** Deletes a food item after manager authorization. */
    private function deleteFood(): array
    {
        $this->requireRole(['manager', 'superadmin']);
        $this->foods->delete((int) $this->input('food_id'));
        return ['ok' => true, 'message' => 'Food removed.'];
    }

    /** Deletes a rating after manager or admin authorization. */
    private function deleteRating(): array
    {
        $this->requireRole(['manager', 'admin', 'superadmin']);
        $this->ratings->delete((int) $this->input('rating_id'));
        return ['ok' => true, 'message' => 'Rating removed.'];
    }

    /** Updates a user role after superadmin authorization. */
    private function updateRole(): array
    {
        $user = $this->requireRole(['admin', 'superadmin']);
        $targetId = (int) $this->input('user_id');
        $role = (string) $this->input('role');
        $allowedRoles = $user['role'] === 'superadmin'
            ? ['consumer', 'manager', 'admin']
            : ['consumer', 'manager'];
        if (!in_array($role, $allowedRoles, true)) {
            throw new RuntimeException('Invalid role.', 422);
        }
        $targetRole = $this->users->roleOf($targetId);
        if ($targetRole === null) {
            throw new RuntimeException('User was not found.', 404);
        }
        if ($targetRole === 'superadmin') {
            throw new RuntimeException('Superadmin roles cannot be changed.', 403);
        }
        if ($user['role'] === 'admin' && !in_array($targetRole, ['consumer', 'manager'], true)) {
            throw new RuntimeException('Admins can only change consumer and manager roles.', 403);
        }
        $this->users->updateRole($targetId, $role);
        return ['ok' => true, 'message' => 'User role updated.'];
    }

    /** Ensures the current user has one of the required roles. */
    private function requireRole(array $roles): array
    {
        $user = $this->session->user();
        // Authorization is enforced server-side for every protected action.
        if (!$user || !in_array($user['role'], $roles, true)) {
            throw new RuntimeException('You are not allowed to perform this action.', 403);
        }

        return $user;
    }

    /** Returns a normalized email from the request. */
    private function email(): string
    {
        return strtolower(trim((string) $this->input('email')));
    }

    /** Reads one value from the POST request. */
    private function input(string $key): mixed
    {
        return $_POST[$key] ?? null;
    }

    /** Sends a JSON response and stops further page rendering. */
    private function respond(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        // Prevent the normal HTML controller from appending to the JSON body.
        exit;
    }
}
