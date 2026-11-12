# Flavour Town
*Petr Halbich, peta.halbich@gmail.com, 12.11.2026, KIV/WEB*

Flavour Town is a PHP/Twig restaurant menu and food-rating web application. It uses
Bootstrap for the responsive UI, MariaDB for persistence, and `fetch()` AJAX
requests for authentication, ratings, menu management, rating moderation, and
role administration.

## Run locally
create db/password.txt file, which must contain only the password for the database

```bash
docker compose up --build
```
Open [http://localhost:8080](http://localhost:8080) and import db/default-export/web-sp.sql into web-sp

Open [http://localhost](http://localhost).

## Demo accounts

All demo accounts use the password shown below:

| Role | Email | Password |
| --- | --- | --- |
| Superadmin | superadmin@flavourtown.test | superadmin |
| Admin | admin@flavourtown.test | adminadmin |
| Admin2 | admin2@flavourtown.test | admin2 |
| Manager | manager@flavourtown.test | manager |
| Consumer | consumer@flavourtown.test | consumer |
| Consumer2 | consumer2@flavourtown.test | consumer2 |

Visitors can register as Consumers. Consumers can submit one editable rating
per dish; Managers and Admins can add, edit, remove dishes and moderate
ratings; Admins can also change consumer/manager roles;
Superadmins can use all application actions except assigning the superadmin
role.

Menu descriptions use a WYSIWYG editor. Menu images are uploaded as JPEG, PNG,
GIF, or WebP files up to 5 MB; uploaded filenames are randomized, stored
outside the web root in a persistent Docker volume, and replaced images are
removed automatically.

## Pages

- `/` - about section and menu overview
- `/?page=food&id=<id>` - menu item details and ratings
- `/?page=rating&food_id=<id>` - consumer rating form
- `/?page=ratings` - ratings authored by the signed-in user
- `/?page=users` - administrator-only user management
- `/?page=food-edit` - manager, admin, or superadmin form for adding a menu item

## Directory tree

```text
.
├── db/
│   ├── default-export/       # Example database export
│   └── password.txt          # Local database secret
├── default-images            # Images for default foods
├── docs                      # Documentation in pdf
├── src/
│   ├── App/
│   │   ├── Controllers/      # HTML and AJAX request handling
│   │   ├── Infrastructure/   # Database connection
│   │   ├── Models/           # Database access for users, food, and ratings
│   │   └── Support/          # Session and CSRF handling
│   ├── templates/            # Twig page templates and shared partials
│   └── index.php             # Application entry point
├── compose.yaml              # Docker services and persistent volumes
├── Dockerfile                # PHP/Apache application image
└── composer.json              # PHP dependencies and autoloading
```

Uploaded images are stored outside the web root in the `uploads-data` Docker
volume.

## Application architecture

The application uses a small MVC-style architecture:

1. `src/index.php` is the front controller. It starts the session, handles
   validated image requests, builds shared services, and dispatches either an
   AJAX action or an HTML page request.
2. Controllers coordinate application behavior. `HomeController` prepares
   Twig pages, while `ApiController` handles POST actions, CSRF checks,
   validation, authorization, and JSON responses.
3. Models encapsulate MariaDB access through PDO. `User`, `Food`, and `Rating`
   contain persistence queries and enforce data-level operations.
4. Twig templates render the UI. Shared layout and JavaScript live in
   `base.twig`; reusable rating markup lives in `rating-list.twig`.
5. `Session` stores the authenticated user and CSRF token. Role checks are
   performed server-side for every protected action.
6. Docker runs PHP and Apache in the `server` container and MariaDB in the
   `db` container. The database uses `db-data`, while uploaded images use the
   persistent `uploads-data` volume.

Requests normally follow this flow:

```text
Browser → src/index.php → Controller → Model → MariaDB
                         └──────────────→ Twig → HTML response
```
