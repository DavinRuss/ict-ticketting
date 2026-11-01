# IT Helpdesk

IT service ticketing app built with Laravel. Built so far: account access (login, logout, forgot/reset password, password change), roles, and admin account management. Ticket features are not built yet.

## Requirements

- PHP 8.3+ with the `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath` and `intl` extensions
- Composer 2
- MariaDB 10.6+ (WAMP ships MariaDB 11.4)
- Node.js 20+ and npm (to build the Tailwind CSS and JS assets)

Tested with Laravel 13.39, PHP 8.3.36, MariaDB 10.6.23, Composer 2.11.1 and Node 22.

## Initial setup

1. Create the database and a dedicated user. Use your own strong password and keep it out of git:

   ```sql
   CREATE DATABASE tickets_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'it_helpdesk'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
   GRANT ALL PRIVILEGES ON tickets_db.* TO 'it_helpdesk'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Install dependencies and configure the environment:

   ```sh
   composer install
   npm install
   npm run build
   cp .env.example .env
   php artisan key:generate
   ```

   Then set `DB_PASSWORD` (and anything else in the table below) in `.env`. On WAMP, MariaDB listens on port 3307, so also set `DB_PORT=3307` (port 3306 there is usually MySQL).

3. Create the tables and the first administrator. The command asks for the password, so it never appears in your shell history:

   ```sh
   php artisan migrate
   php artisan app:create-admin admin@yourcompany.com --name="IT Admin"
   ```

   If the account already exists, promote it instead (asks for confirmation): `php artisan app:promote-admin someone@yourcompany.com`.

4. Start the app with `php artisan serve` and open http://localhost:8000.

`php artisan db:seed` adds three local sample accounts, all with the password `password`: `admin@example.com` (admin), `tech@example.com` (technician) and `test@example.com` (user). Use them for local development only. The seeder skips them when `APP_ENV=production`.

## Environment variables

`.env` is git-ignored. Never commit it or put real credentials in `.env.example`.

| Variable | Example | Notes |
| --- | --- | --- |
| `APP_NAME` | `"IT Helpdesk"` | Shown in page titles and emails. |
| `APP_ENV` | `local` / `production` | Production enforces stronger passwords (see below). |
| `APP_KEY` | generated | Set by `php artisan key:generate`. Keep it secret. |
| `APP_DEBUG` | `true` locally, `false` in production | Must be `false` in production so stack traces are never shown. |
| `APP_URL` | `http://localhost:8000` | Used to build password-reset links. |
| `DB_CONNECTION` | `mariadb` | Laravel's MariaDB driver (uses `pdo_mysql`). |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | WAMP's MariaDB listens on `3307`. |
| `DB_DATABASE` | `tickets_db` | |
| `DB_USERNAME` | `it_helpdesk` | |
| `DB_PASSWORD` | *(your password)* | Required. Left empty in `.env.example` on purpose. |
| `SESSION_DRIVER` | `database` | Sessions are stored in the `sessions` table. |
| `SESSION_LIFETIME` | `120` | Idle minutes before you're signed out. |
| `SESSION_SECURE_COOKIE` | `true` in production | Send the session cookie over HTTPS only. |
| `MAIL_MAILER` | `log` | In development, emails (including reset links) are written to `storage/logs/laravel.log`. Use a real SMTP mailer in production. |
| `MAIL_FROM_ADDRESS` | `helpdesk@yourcompany.com` | Sender address for password-reset emails. |

## Account access

- **No public sign-up.** As the product brief requires, accounts are created by an admin (Accounts page) or, locally, by the seeder. The first admin comes from `php artisan app:create-admin`.
- **Login** is rate-limited to 5 failed attempts per email and IP, and the session ID is regenerated on login. Deactivated accounts (`is_active = false`) can't log in and see a "deactivated" message.
- **Logout** invalidates the session and regenerates the CSRF token.
- **Forgot password** gives the same response whether or not the email has an account, and is limited to 6 requests per minute per IP. Reset links use Laravel's password broker and expire after 60 minutes.
- **Password change**: signed-in users can change their password on the Profile page, which requires their current password.
- **Password rules**: at least 8 characters. In production, passwords must also mix upper and lower case, include a number, and not appear in known data breaches.
- **No account deletion**: accounts are deactivated by an admin instead, so account and ticket history is kept.

## Roles and access

Each account has one role, stored in `users.role`: `user` (default), `technician` or `admin`.

- **Route protection**: the `role` middleware (`App\Http\Middleware\EnsureRole`) guards each role's route group, for example `role:admin` on `/admin/*` and `role:technician` on `/technician`. Other roles get a 403 page and guests are sent to login.
- **Policy**: `App\Policies\UserPolicy` decides who can list, create and edit accounts (admins only) and who can change a role or active flag (admins, never on their own account).
- **Role and active flag** aren't mass-assignable. The profile form ignores them, and only the admin account screens change them.
- **Deactivation** takes effect immediately: a deactivated user is signed out on their next request and can't log in again.

| Area | User | Technician | Admin |
| --- | --- | --- | --- |
| Dashboard, Profile, password change | yes | yes | yes |
| Technician workspace (`/technician`) | no | yes | no |
| Accounts (`/admin/users`): list, search, create, edit, change role, deactivate | no | no | yes |

**Admin account screens**: admins can search accounts and filter them by role, create an account with a role and an initial password, and edit name, email, phone, role and active status. Safeguards:
- Admins can't change their own role or deactivate themselves, so there's always at least one active admin.
- Emails must be unique.
- Roles must be one of the three values.
- Passwords follow the password rules below.

There's no delete. Deactivate accounts instead.

## Tests

```sh
php artisan test           # uses in-memory SQLite (phpunit.xml)
./vendor/bin/pint --test   # code style
```

To run the tests against MariaDB, create an empty `tickets_db_test` database and run:

```sh
DB_CONNECTION=mariadb DB_DATABASE=tickets_db_test php artisan test
```
