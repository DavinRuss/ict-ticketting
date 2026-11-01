# IT Helpdesk (ict-ticketting)

IT service ticketing web application built with Laravel. Users submit IT requests, admins assign them to technicians, technicians work and resolve them, and users review resolved tickets. See [AGENTS.md](AGENTS.md) for the full product brief and project rules.

## Requirements

- PHP 8.3+ with `pdo_mysql`, `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`
- Composer 2
- MySQL 8

Built and tested with Laravel 13.39, PHP 8.3.36, MySQL 8.0.46.

## How the project was created

```sh
composer create-project laravel/laravel it-helpdesk
```

The generated app lives at the root of this repository.

## Local setup

1. Install PHP dependencies:

   ```sh
   composer install
   ```

2. Create the MySQL database and a dedicated user (utf8mb4). Pick your own password:

   ```sql
   CREATE DATABASE it_helpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'it_helpdesk'@'localhost' IDENTIFIED BY 'change-me';
   GRANT ALL PRIVILEGES ON it_helpdesk.* TO 'it_helpdesk'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. Configure the environment:

   ```sh
   cp .env.example .env        # Windows: copy .env.example .env
   php artisan key:generate
   ```

   Then set `DB_PASSWORD` in `.env` to the password from step 2. `.env.example` already sets `APP_NAME="IT Helpdesk"`, `APP_URL=http://localhost:8000`, the MySQL `DB_*` settings and `MAIL_MAILER=log` (emails are written to `storage/logs/laravel.log` in development). Never commit `.env`.

4. Create the tables and sample data:

   ```sh
   php artisan migrate:fresh --seed
   ```

5. Run the app:

   ```sh
   php artisan serve
   ```

## Sample accounts (local development only)

All seeded accounts use the password `password`. They exist only for local development and must never be created in production.

| Role       | Email                                                    |
| ---------- | -------------------------------------------------------- |
| Admin      | admin@example.com                                        |
| Technician | tech1@example.com, tech2@example.com                     |
| User       | user1@example.com, user2@example.com, user3@example.com  |

The seeder also creates 6 categories (Hardware, Software, Network, Email, Printer, Account/Access) and 15 sample tickets across every status, with their activity history, a few comments and reviews.

## Roles

| Role         | Can                                                                                       |
| ------------ | ----------------------------------------------------------------------------------------- |
| `user`       | Create and track their own tickets, comment on them, review their own resolved tickets    |
| `technician` | See only tickets assigned to them, update status, add notes                              |
| `admin`      | See everything, manage users, assign and reassign tickets, view reviews and reports       |

## Ticket workflow

```
open -> assigned -> in_progress -> resolved -> closed
```

The allowed transitions are defined in one place: `App\Enums\TicketStatus::canTransitionTo()`. Every status change and assignment is recorded in `ticket_activities`.

Ticket references (`TKT-000123`) are generated from the auto-increment id right after insert, so concurrent inserts cannot produce duplicates.

## Tests

```sh
php artisan test
```

Tests use an in-memory SQLite database by default (see `phpunit.xml`), so no MySQL setup is needed to run them.
