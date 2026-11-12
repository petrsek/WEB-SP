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
                'rating-visibility' => $this->setRatingVisibility(),
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

    /** Validates and saves an authorized food item's changes. */
    private function saveFood(): array
    {
        $this->requireRole(['manager', 'admin', 'superadmin']);
        $foodId = (int) $this->input('food_id');
        $existingFood = $foodId > 0 ? $this->foods->findWithRatings($foodId) : null;
        if ($foodId > 0 && !$existingFood) {
            throw new RuntimeException('Food was not found.', 404);
        }
        $description = $this->sanitizeDescription((string) $this->input('description'));
        $newImage = $this->uploadImage();
        $imageUrl = $newImage ?? ($existingFood['image_url'] ?? null);
        $values = [trim((string) $this->input('name')), $description, trim((string) $this->input('category')), (float) $this->input('price'), $imageUrl];
        if ($values[0] === '' || $values[1] === '' || $values[2] === '' || $values[3] <= 0) {
            if ($newImage !== null) {
                $this->removeUploadedImage($newImage);
            }
            throw new RuntimeException('Name, description, category and a positive price are required.', 422);
        }
        try {
            $this->foods->save($foodId ?: null, ...$values);
        } catch (Throwable $exception) {
            if ($newImage !== null) {
                $this->removeUploadedImage($newImage);
            }
            throw $exception;
        }
        if ($newImage !== null && isset($existingFood['image_url'])) {
            $this->removeUploadedImage((string) $existingFood['image_url']);
        }
        return ['ok' => true, 'message' => 'Menu updated.'];
    }

    /** Deletes a food item after food-management authorization. */
    private function deleteFood(): array
    {
        $this->requireRole(['manager', 'admin', 'superadmin']);
        $foodId = (int) $this->input('food_id');
        if ($foodId < 1) {
            throw new RuntimeException('Food was not found.', 404);
        }
        $this->foods->delete($foodId);
        return ['ok' => true, 'message' => 'Food removed.'];
    }

    /** Deletes a rating by its author or a superadmin. */
    private function deleteRating(): array
    {
        $user = $this->requireRole(['consumer', 'manager', 'admin', 'superadmin']);
        $ratingId = (int) $this->input('rating_id');
        if ($ratingId < 1) {
            throw new RuntimeException('Rating was not found.', 404);
        }
        if (in_array($user['role'], ['consumer', 'manager', 'admin'], true)) {
            if (!$this->ratings->deleteForUser($ratingId, (int) $user['id'])) {
                throw new RuntimeException('You can only remove your own ratings.', 403);
            }
        } else {
            $this->ratings->delete($ratingId);
        }
        return ['ok' => true, 'message' => 'Rating removed.'];
    }

    /** Hides or shows rating text for all viewers. */
    private function setRatingVisibility(): array
    {
        $this->requireRole(['manager', 'admin', 'superadmin']);
        $ratingId = (int) $this->input('rating_id');
        if ($ratingId < 1) {
            throw new RuntimeException('Rating was not found.', 404);
        }
        $visible = (string) $this->input('visible') === '1';
        if (!$this->ratings->setVisibility($ratingId, $visible)) {
            throw new RuntimeException('Rating was not found.', 404);
        }

        return ['ok' => true, 'message' => $visible ? 'Rating text shown.' : 'Rating text hidden.'];
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

    /** Stores a validated image upload and returns its public relative path. */
    private function uploadImage(): ?string
    {
        $upload = $_FILES['image'] ?? null;
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
            throw new RuntimeException('The image upload failed.', 422);
        }
        if ((int) ($upload['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('Images must be 5 MB or smaller.', 422);
        }
        $imageInfo = @getimagesize((string) $upload['tmp_name']);
        $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Only JPEG, PNG, GIF and WebP images are allowed.', 422);
        }

        $directory = $this->uploadsDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('The image upload directory is unavailable.', 500);
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $path = $directory . '/' . $filename;
        if (!move_uploaded_file((string) $upload['tmp_name'], $path)) {
            throw new RuntimeException('The image could not be saved.', 500);
        }

        return '?image=' . $filename;
    }

    /** Removes a locally stored uploaded image without touching external URLs. */
    private function removeUploadedImage(string $imageUrl): void
    {
        if (!preg_match('/^\?image=([a-f0-9]{32}\.(jpg|png|gif|webp))$/', $imageUrl, $matches)) {
            return;
        }
        $path = $this->uploadsDirectory() . '/' . $matches[1];
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('The old image could not be removed.', 500);
        }
    }

    /** Returns the private persistent upload directory. */
    private function uploadsDirectory(): string
    {
        return rtrim((string) (getenv('UPLOADS_PATH') ?: '/var/lib/flavour-town/uploads'), '/');
    }

    /** Keeps only the formatting tags supported by the WYSIWYG editor. */
    private function sanitizeDescription(string $description): string
    {
        $description = strip_tags($description, '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><blockquote>');

        return (string) preg_replace('/<(\/?)(p|br|strong|em|u|ol|ul|li|h1|h2|h3|blockquote)(?:\s[^>]*)?>/i', '<$1$2>', $description);
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
