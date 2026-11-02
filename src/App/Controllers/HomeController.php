<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Food;
use App\Models\Rating;
use App\Models\User;
use App\Support\Session;
use Twig\Environment;

final class HomeController
{
    public function __construct(
        private readonly Environment $view,
        private readonly Session $session,
        private readonly Food $foods,
        private readonly Rating $ratings,
        private readonly User $users
    ) {
    }

    public function index(): void
    {
        $user = $this->session->user();
        $users = $user && in_array($user['role'], ['admin', 'superadmin'], true) ? $this->users->all() : [];
        echo $this->view->render('home.twig', [
            'user' => $user,
            'foods' => $this->foods->allWithRatings(),
            'ratings' => $this->ratings->all(),
            'users' => $users,
            'csrf' => $this->session->csrfToken(),
        ]);
    }
}
