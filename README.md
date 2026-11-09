# Flavorly

Flavorly is a PHP/Twig restaurant menu and food-rating web application. It uses
Bootstrap for the responsive UI, MariaDB for persistence, and `fetch()` AJAX
requests for authentication, ratings, menu management, rating moderation, and
role administration.

## Run locally

```bash
docker compose up --build
```

Open [http://localhost](http://localhost). The database schema and sample menu
are created automatically on the first request.

## Demo accounts

All demo accounts use the password shown below:

| Role | Email | Password |
| --- | --- | --- |
| Superadmin | superadmin@flavourtown.test | superadmin |
| Admin | admin@flavourtown.test | admin |
| Manager | manager@flavourtown.test | manager |

Visitors can register as Consumers. Consumers can submit one editable rating
per dish; Managers can add, edit, remove dishes and moderate ratings; Admins
can inspect users, foods, and ratings; Superadmins can change user roles.

## Pages

- `/` - about section and menu overview
- `/?page=food&id=<id>` - menu item details and ratings
- `/?page=rating&food_id=<id>` - consumer rating form
- `/?page=users` - administrator-only user management
