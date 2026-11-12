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
- `/?page=food-edit` - manager or superadmin form for adding a menu item
