<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Food;
use App\Models\Rating;
use App\Models\User;
use App\Support\Session;
use RuntimeException;
use Throwable;

final class ApiController
{
    public function __construct(
        private readonly Session $session,
        private readonly User $users,
        private readonly Food $foods,
        private readonly Rating $ratings
    ) {
    }

    public function handle(string $action): void
    {
        try {
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

    private function login(): array
    {
        $user = $this->users->authenticate($this->email(), (string) $this->input('password'));
        $this->session->signIn($user);
        return ['ok' => true, 'redirect' => './'];
    }

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

    private function logout(): array
    {
        $this->session->signOut();
        return ['ok' => true, 'redirect' => './'];
    }

    private function rate(): array
    {
        $this->requireRole(['consumer']);
        $score = (int) $this->input('score');
        if ($score < 1 || $score > 5 || (int) $this->input('food_id') < 1) {
            throw new RuntimeException('Choose a rating from 1 to 5.', 422);
        }
        $this->ratings->save((int) $this->input('food_id'), (int) $this->session->user()['id'], $score, trim((string) $this->input('comment')));
        return ['ok' => true, 'message' => 'Your rating was saved.'];
    }

    private function saveFood(): array
    {
        $this->requireRole(['manager']);
        $values = [trim((string) $this->input('name')), trim((string) $this->input('description')), trim((string) $this->input('category')), (float) $this->input('price'), trim((string) $this->input('image_url')) ?: null];
        if ($values[0] === '' || $values[1] === '' || $values[2] === '' || $values[3] <= 0) {
            throw new RuntimeException('Name, description, category and a positive price are required.', 422);
        }
        $this->foods->save((int) $this->input('food_id') ?: null, ...$values);
        return ['ok' => true, 'message' => 'Menu updated.'];
    }

    private function deleteFood(): array
    {
        $this->requireRole(['manager']);
        $this->foods->delete((int) $this->input('food_id'));
        return ['ok' => true, 'message' => 'Food removed.'];
    }

    private function deleteRating(): array
    {
        $this->requireRole(['manager', 'admin']);
        $this->ratings->delete((int) $this->input('rating_id'));
        return ['ok' => true, 'message' => 'Rating removed.'];
    }

    private function updateRole(): array
    {
        $this->requireRole(['superadmin']);
        $role = (string) $this->input('role');
        if (!in_array($role, ['consumer', 'manager', 'admin', 'superadmin'], true)) {
            throw new RuntimeException('Invalid role.', 422);
        }
        $this->users->updateRole((int) $this->input('user_id'), $role);
        return ['ok' => true, 'message' => 'User role updated.'];
    }

    private function requireRole(array $roles): void
    {
        $user = $this->session->user();
        if (!$user || !in_array($user['role'], $roles, true)) {
            throw new RuntimeException('You are not allowed to perform this action.', 403);
        }
    }

    private function email(): string
    {
        return strtolower(trim((string) $this->input('email')));
    }

    private function input(string $key): mixed
    {
        return $_POST[$key] ?? null;
    }

    private function respond(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }
}
