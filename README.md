# IT Helpdesk

IT service ticketing app built with Laravel. Built so far: account access (login, logout, forgot/reset password, password change), roles, admin account management, ticket submission with a user dashboard, admin ticket management (assignment and status changes), and the technician workspace (assigned tickets, work notes, starting and resolving work).

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

   Then set `DB_PASSWORD` (and anything else in the table below) in `.env`. On WAMP, MariaDB listens on port 3307, so also set `DB_PORT=3307` (port 3306 there is usually MySQL). Tables are always created as InnoDB (`config/database.php`, override with `DB_ENGINE`), because WAMP's MariaDB defaults to MyISAM, which has no foreign keys or transactions and fails on long indexes ("Specified key was too long").

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
| My tickets (`/tickets`): submit, list and open **own** tickets | yes | yes | yes |
| Technician workspace (`/technician`): **own assigned** tickets, work notes, start and resolve work | no | yes | no |
| Accounts (`/admin/users`): list, search, create, edit, change role, deactivate | no | no | yes |
| All tickets (`/admin/tickets`): list and filter every ticket, assign/reassign technicians, change status | no | no | yes |

**Admin account screens**: admins can search accounts and filter them by role, create an account with a role and an initial password, and edit name, email, phone, role and active status. Safeguards:
- Admins can't change their own role or deactivate themselves, so there's always at least one active admin.
- Emails must be unique.
- Roles must be one of the three values.
- Passwords follow the password rules below.

There's no delete. Deactivate accounts instead.

## Tickets

- **Submitting**: any signed-in user opens **New ticket** and enters a title, description, category (active categories only) and priority (`low`, `medium`, `high`, `urgent`). The ticket always starts as `open`, unassigned, owned by the person who submitted it. Status, owner, technician and reference sent by the browser are ignored.
- **Reference**: each ticket gets `TKT-000123`, built from its database id right after insert, so references never collide.
- **History**: every ticket keeps an append-only log in `ticket_activities`. Submitting writes a `created` entry; the ticket page lists these under *Updates*.
- **Privacy**: `App\Policies\TicketPolicy` lets users open only their own tickets. Opening someone else's ticket by changing the URL gives a 403. The list and the dashboard counts are always scoped to the signed-in user.
- **Status workflow**: `App\Enums\TicketStatus` is the single place that defines the allowed transitions (`open -> assigned -> in_progress -> resolved -> closed`; reassigning keeps a ticket `assigned`). Users can't change status, except closing their own resolved ticket (below).

### Technician workspace (`/technician`, "My assignments" in the nav)

- **Visibility**: a technician sees only tickets **currently** assigned to them (`TicketPolicy::work()`; lists start from `assignedTickets()`). After a reassignment the previous technician gets a 403. Admins use `/admin/tickets` instead.
- **Dashboard and list**: counts by status, a "Needs your work" list (assigned or in progress), and a 15-per-page list filterable by status (including "Needs work") and priority.
- **Status changes**: a technician may only move their ticket `assigned -> in_progress` (stamps `started_at`) and `in_progress -> resolved` (stamps `resolved_at`). They can't assign, reassign, close, skip or reverse a status, or change the requester; anything else gets a clear error. `TicketStatus::transitionsFor(UserRole)` defines which statuses each role may set, and `TicketService::changeStatus()` enforces it under a row lock (also rejecting a technician whose ticket was reassigned meanwhile).
- **Work notes**: internal notes recorded as `ticket_activities` rows (`action = work_note`) with author and time. They show to the technician and admins with an "Internal" badge and are **hidden from the requester**. Notes typed with a status change are shown to the requester. Closed tickets take no new notes.
- **Resolution choice**: technicians mark work **Resolved directly**. There's no separate "awaiting confirmation" status. The requester's confirmation is closing the resolved ticket (or an admin closes it), which keeps the agreed `open -> assigned -> in_progress -> resolved -> closed` lifecycle unchanged.

### Closing and satisfaction reviews

- **Confirming the fix**: on a `resolved` ticket the requester sees **Confirm fix and close** (`PUT /tickets/{ticket}/close`, `TicketPolicy::close()`: owner only). It goes through `TicketService::changeStatus()` using `TicketStatus::transitionsFor(UserRole::User)` (`resolved -> closed` only). It stamps `closed_at` and logs a `status_changed` row by the requester. Admins can also close from their status form.
- **Reviews**: the requester can rate a `resolved` or `closed` ticket 1–5 stars, with an optional comment of up to 1000 characters (`TicketStatus::acceptsReviews()`). Reviewing is separate from closing, so it can happen before or after.
- **Rules**: `ReviewPolicy` allows only the ticket's requester to create a review, and only the review's author to edit it. Everyone else, admins included, gets a 403. `StoreReviewRequest` checks the rating server-side (`between:1,5`). `TicketService::submitReview()` locks the ticket and rejects unresolved tickets and second reviews with a clear message. The `reviews.ticket_id` unique index also blocks duplicates at the database level. The ticket and author always come from the route and the signed-in user, never from form input.
- **Who sees them**: the requester sees and can edit their own review on their ticket page. Admins see it on the admin ticket page and on **Feedback** (`/admin/reviews`): 15 per page, filters by rating and technician, and the average rating for the current filter. Technicians don't see reviews.

### Admin ticket management (`/admin/tickets`, "All tickets" in the nav)

- **List**: every ticket, 15 per page, filterable by status, priority, category and technician (including "Not assigned").
- **Assign / reassign**: only to an **active technician** account; users, admins, deactivated technicians and unknown ids are rejected. Only `open` or `assigned` tickets can be (re)assigned. Assigning sets `assigned_to`, `assigned_by` (the acting admin), `assigned_at` and status `assigned`.
- **Status changes**: the admin picks the next status from those `TicketStatus::transitionsFor(UserRole::Admin)` allows (`assigned -> in_progress -> resolved -> closed`). Anything else, including stale forms, gets "A ticket cannot move from X to Y." Moving to `in_progress`, `resolved` or `closed` stamps `started_at`, `resolved_at` or `closed_at`.
- **History**: each assignment, reassignment and status change appends a `ticket_activities` row with the acting user, time, from/to status, from/to technician (`from_technician_id`, `to_technician_id`) and an optional note. Notes are shown to the requester on their ticket page.
- **Consistency**: `App\Services\TicketService::assign()` and `changeStatus()` update the ticket and write the history row in one database transaction, with the ticket row locked, so checks use the current state and a failed history write rolls back the change.
- **Categories**: `CategorySeeder` adds Hardware, Software, Network, Email, Printer and Account/Access. It runs in every environment (including production) and is safe to re-run: `php artisan db:seed --class=CategorySeeder`.
- **Sample data**: outside production, `php artisan migrate:fresh --seed` also adds 5 tickets for `test@example.com`: one assigned to `tech@example.com`, in progress and with a work note, one assigned, one resolved by the technician with a 5-star review, and two open.

## Tests

```sh
php artisan test           # uses in-memory SQLite (phpunit.xml)
./vendor/bin/pint --test   # code style
```

To run the tests against MariaDB, create an empty `tickets_db_test` database and run:

```sh
DB_CONNECTION=mariadb DB_DATABASE=tickets_db_test php artisan test
```
