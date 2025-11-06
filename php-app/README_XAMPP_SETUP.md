# VoltSpace PHP App (XAMPP)

- Copy the contents of `php-app/` into `XAMPP/htdocs/voltspace/` so that `index.php` lives at `htdocs/voltspace/index.php`.
- Start Apache and MySQL in XAMPP.
- In phpMyAdmin:
  - Create database `voltSpace_db` (utf8mb4).
  - Import `php-app/sql/schema.sql` then `php-app/sql/seed.sql`.
- Edit `php-app/config.php` as needed for DB credentials and `AI_SERVICE_URL` (default `http://127.0.0.1:8000`).
- Visit `http://localhost/voltspace/pages/login.php`.
  - Demo user: `demo@voltspace.local` / `Demo123!` (first login upgrades hash).

Notes
- If you already placed this repo under `htdocs/voltspace/`, you can run it as-is.
- Ensure `php_curl` is enabled for calling the AI service.

