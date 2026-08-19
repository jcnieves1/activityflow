# Installing ActivityFlow

Works with any standard Apache + PHP 8.2+ + MySQL 8+ stack: XAMPP, WAMP,
MAMP, LAMP, or shared hosting with cPanel-style file/database access.

## 1. Place the files

Copy the whole `activityflow` folder (this project's root) into your server's
web root, e.g.:

- XAMPP: `C:\xampp\htdocs\activityflow`
- WAMP: `C:\wamp64\www\activityflow`
- LAMP: `/var/www/html/activityflow`
- Shared hosting: your `public_html/activityflow` (or a subdomain root)

No build step is required — it's plain PHP/HTML/CSS/JS.

## 2. Create the database

Using phpMyAdmin, Adminer, or the `mysql` CLI, run the two SQL files in
order:

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p activityflow < database/seed.sql
```

`schema.sql` creates the `activityflow` database and all tables; `seed.sql`
adds sample departments, categories, people, projects, and planned/unplanned
demo activities.

## 3. Configure the app

```bash
cp config/config.sample.php config/config.php
```

Edit `config/config.php` (or set the equivalent environment variables) with
your database credentials and base URL:

```php
'db' => ['host' => '127.0.0.1', 'name' => 'activityflow', 'user' => 'root', 'pass' => 'yourpassword'],
'app' => ['base_url' => 'http://localhost/activityflow'],
```

`config/config.php` is denied by `.htaccess` and should never be committed to
version control or exposed publicly.

## 4. Create demo login accounts

Seed data includes people/requesters, but login accounts need PHP's
`password_hash()`, so they're created by a small script instead of raw SQL:

```bash
php database/seed_users.php
```

This prints the demo accounts it created. All of them use the password
`Password123!` and the recovery answer `Chicago` — **for development only.**
Change or delete these before any real deployment.

## 5. Open the app

Visit `http://localhost/activityflow/` (or your configured base URL) and log
in with one of the seeded accounts, or register a new one.

## Apache notes

- If your host lets you set a custom document root, you can point it at this
  folder directly — `.htaccess` already denies direct access to `config/`,
  `includes/`, and `database/`.
- Ensure `mod_rewrite` and `mod_headers` are enabled (both are on by default
  in XAMPP/WAMP).
- PHP needs the `pdo_mysql`, `session`, `json`, and `gd` extensions (all
  enabled by default in standard PHP builds). `gd` is used to resize/crop
  profile photos on upload (see `includes/models/avatars.php`) — if it's
  missing, photo uploads on the Profile page will fail with a clear error,
  but the rest of the app is unaffected.
- The `uploads/avatars/` folder must be writable by the web server user —
  that's where processed profile photos are stored (see
  `includes/models/avatars.php`).

## Production checklist

- Set `'env' => 'production'` in `config/config.php` to suppress detailed
  error output.
- Serve over HTTPS — session cookies are marked `Secure` automatically when
  the request is HTTPS.
- Change all demo passwords/recovery answers or remove the demo accounts.
- Review `docs/SECURITY.md`.
