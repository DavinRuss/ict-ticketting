# IT Helpdesk

IT service ticketing app built with Laravel. This phase covers the project foundation and account access: sign-up, login, logout, forgot/reset password and password change. Ticket features are not built yet.

## Requirements

- PHP 8.3+ with the `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath` and `intl` extensions
- Composer 2
- MySQL 8
- Node.js 20+ and npm (to build the Tailwind CSS and JS assets)

Tested with Laravel 13.39, PHP 8.3.36, MySQL 8.0.46, Composer 2.11.1 and Node 22.

## Initial setup

1. Create the database and a dedicated user. Use your own strong password and keep it out of git:

   ```sql
   CREATE DATABASE it_helpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'it_helpdesk'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
   GRANT ALL PRIVILEGES ON it_helpdesk.* TO 'it_helpdesk'@'localhost';
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

   Then set `DB_PASSWORD` (and anything else in the table below) in `.env`.

3. Create the tables and the first administrator. The command asks for the password, so it never appears in your shell history:

   ```sh
   php artisan migrate
   php artisan app:create-admin admin@yourcompany.com --name="IT Admin"
   ```

4. Start the app with `php artisan serve` and open http://localhost:8000.

`php artisan db:seed` adds one local test account (`test@example.com` / `password`). Use it for local development only, never in production.

## Environment variables

`.env` is git-ignored. Never commit it or put real credentials in `.env.example`.

| Variable | Example | Notes |
| --- | --- | --- |
| `APP_NAME` | `"IT Helpdesk"` | Shown in page titles and emails. |
| `APP_ENV` | `local` / `production` | Production enforces stronger passwords (see below). |
| `APP_KEY` | generated | Set by `php artisan key:generate`. Keep it secret. |
| `APP_DEBUG` | `true` locally, `false` in production | Must be `false` in production so stack traces are never shown. |
| `APP_URL` | `http://localhost:8000` | Used to build password-reset links. |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | |
| `DB_DATABASE` | `it_helpdesk` | |
| `DB_USERNAME` | `it_helpdesk` | |
| `DB_PASSWORD` | *(your password)* | Required. Left empty in `.env.example` on purpose. |
| `SESSION_DRIVER` | `database` | Sessions are stored in the `sessions` table. |
| `SESSION_LIFETIME` | `120` | Idle minutes before you're signed out. |
| `SESSION_SECURE_COOKIE` | `true` in production | Send the session cookie over HTTPS only. |
| `MAIL_MAILER` | `log` | In development, emails (including reset links) are written to `storage/logs/laravel.log`. Use a real SMTP mailer in production. |
| `MAIL_FROM_ADDRESS` | `helpdesk@yourcompany.com` | Sender address for password-reset emails. |

## Account access

- **Registration** is open, and every sign-up becomes a regular, active `user`. The `role` and `is_active` fields aren't mass-assignable, so anything the form sends for them is ignored. Technicians and admins are never created through sign-up: the first admin comes from `php artisan app:create-admin`, and admin-managed accounts arrive in a later phase.
- **Login** is rate-limited to 5 failed attempts per email and IP, and the session ID is regenerated on login. Deactivated accounts (`is_active = false`) can't log in and see a "deactivated" message.
- **Logout** invalidates the session and regenerates the CSRF token.
- **Forgot password** gives the same response whether or not the email has an account, and is limited to 6 requests per minute per IP. Reset links use Laravel's password broker and expire after 60 minutes.
- **Password change**: signed-in users can change their password on the Profile page, which requires their current password.
- **Password rules**: at least 8 characters. In production, passwords must also mix upper and lower case, include a number, and not appear in known data breaches.
- **No self-service account deletion**: Breeze's "Delete account" feature was removed to keep account and ticket history. Accounts will be deactivated by admins instead.

Roles in the database: `user`, `technician`, `admin` (default `user`). Role-based pages come in later phases.

## Tests

```sh
php artisan test           # uses in-memory SQLite (phpunit.xml)
./vendor/bin/pint --test   # code style
```

To run the tests against MySQL, create an empty `it_helpdesk_test` database and run:

```sh
DB_CONNECTION=mysql DB_DATABASE=it_helpdesk_test php artisan test
```
