# Flavour Town

Flavour Town is a PHP/Twig restaurant menu and food-rating web application. It uses
Bootstrap for the responsive UI, MariaDB for persistence, and `fetch()` AJAX
requests for authentication, ratings, menu management, rating moderation, and
role administration.

## Run locally
create db/password.txt file, which must contain only the password for the database

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
| Admin | admin@flavourtown.test | adminadmin |
| Manager | manager@flavourtown.test | manager |

Visitors can register as Consumers. Consumers can submit one editable rating
per dish; Managers and Admins can add, edit, remove dishes and moderate
ratings; Admins can also change consumer/manager roles;
Superadmins can use all application actions except assigning the superadmin
role.

## Pages

- `/` - about section and menu overview
- `/?page=food&id=<id>` - menu item details and ratings
- `/?page=rating&food_id=<id>` - consumer rating form
- `/?page=ratings` - ratings authored by the signed-in user
- `/?page=users` - administrator-only user management
- `/?page=food-edit` - manager or superadmin form for adding a menu item
