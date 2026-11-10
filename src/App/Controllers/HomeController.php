<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Food;
use App\Models\Rating;
use App\Models\User;
use App\Support\Session;
use Twig\Environment;
use RuntimeException;

/** Handles requests for the rendered restaurant menu page. */
final class HomeController
{
    /** Injects the view renderer, session, and required models. */
    public function __construct(
        private readonly Environment $view,
        private readonly Session $session,
        private readonly Food $foods,
        private readonly Rating $ratings,
        private readonly User $users
    ) {
    }

    /** Dispatches the requested page. */
    public function dispatch(string $page, ?int $id = null): void
    {
        match ($page) {
            'food' => $this->food($id ?? 0),
            'food-edit' => $this->foodEdit($id ?? 0),
            'rating' => $this->rating($id ?? 0),
            'ratings' => $this->userRatings(),
            'users' => $this->users(),
            default => $this->index(),
        };
    }

    /** Renders the public about section and menu overview. */
    private function index(): void
    {
        $this->render('index.twig', ['foods' => $this->foods->allWithRatings()]);
    }

    /** Renders a food detail page and its ratings. */
    private function food(int $id): void
    {
        $food = $this->foods->findWithRatings($id);
        if (!$food) {
            throw new RuntimeException('Food was not found.', 404);
        }
        $this->render('food.twig', ['food' => $food, 'ratings' => $this->ratings->forFood($id)]);
    }

    /** Renders the create or edit form for a user's rating. */
    private function rating(int $foodId): void
    {
        $user = $this->session->user();
        if (!$user || !in_array($user['role'], ['consumer', 'superadmin'], true)) {
            throw new RuntimeException('Only consumers and superadmins can edit ratings.', 403);
        }
        $food = $this->foods->findWithRatings($foodId);
        if (!$food) {
            throw new RuntimeException('Food was not found.', 404);
        }
        $this->render('rating.twig', [
            'food' => $food,
            'rating' => $this->ratings->forUserAndFood((int) $user['id'], $foodId),
        ]);
    }

    /** Renders all ratings authored by the signed-in user. */
    private function userRatings(): void
    {
        $user = $this->session->user();
        if (!$user) {
            throw new RuntimeException('You must be signed in to view your ratings.', 403);
        }

        $this->render('ratings.twig', ['ratings' => $this->ratings->forUser((int) $user['id'])]);
    }

    /** Renders the manager form for creating or editing a menu item. */
    private function foodEdit(int $id): void
    {
        $user = $this->session->user();
        if (!$user || !in_array($user['role'], ['manager', 'superadmin'], true)) {
            throw new RuntimeException('Only managers and superadmins can edit menu items.', 403);
        }
        $food = $id > 0 ? $this->foods->findWithRatings($id) : null;
        if ($id > 0 && !$food) {
            throw new RuntimeException('Food was not found.', 404);
        }
        $this->render('food-edit.twig', ['food' => $food]);
    }

    /** Renders user management for administrators only. */
    private function users(): void
    {
        $user = $this->session->user();
        if (!$user || !in_array($user['role'], ['admin', 'superadmin'], true)) {
            throw new RuntimeException('Only administrators can manage users.', 403);
        }
        $this->render('users.twig', ['users' => $this->users->all()]);
    }

    /** Renders a page with shared layout data. */
    private function render(string $template, array $data = []): void
    {
        echo $this->view->render($template, [
            ...$data,
            'user' => $this->session->user(),
            'csrf' => $this->session->csrfToken(),
        ]);
    }
}
