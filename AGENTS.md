# Project rules

- Follow the existing project. Before changing code, inspect the repository and its instructions. Respect existing architecture and conventions unless they conflict with a requirement in this prompt.
- Keep each phase focused. Implement only the current phase. Do not start later phases or add unrelated features.
- Prefer simple Laravel conventions. Use Laravel’s built-in tools and clear, conventional code. Add a package only when it solves a real requirement that the framework does not already handle well.
- Keep business rules in one place. Centralize ticket status transitions and role-based access decisions so controllers, pages, and tests do not implement conflicting rules.
- Protect every record on the server. Check authorization for every view, update, assignment, status change, and review. Never rely only on hidden buttons, frontend checks, or unguessable IDs.
- Validate at the boundary. Validate and authorize incoming requests before changing data. Do not trust role, owner, status, or technician IDs submitted by the browser.
- Use database safeguards. Add appropriate foreign keys, indexes, nullability, uniqueness rules, and delete behavior. Use transactions when one operation updates multiple related records.
- Preserve history. Do not silently erase ticket assignment, status, or work-note history when a ticket changes. Record who made important changes and when.
- Handle errors clearly. Show useful validation and access-denied feedback without exposing stack traces, secrets, or private ticket details.
- Keep private data private. Users see only their own tickets and reviews. Technicians see only their assigned tickets. Admin capabilities must be explicitly protected.
- Make changes reviewable. Avoid broad rewrites. Keep naming consistent, remove dead code introduced by the current phase, and explain any assumptions or tradeoffs.
- Do not fake completion. Run the checks relevant to the current phase, report the exact results, and disclose anything not verified.
- Do not commit secrets or real user data. Use environment variables for credentials and safe sample data for local development.
- Update documentation when behavior changes. Keep setup instructions, environment-variable examples, and role or workflow descriptions aligned with the implementation.
- Always update both the local and GitHub repo. After every push or merge on GitHub, update the local copy at `C:\Users\HP\Desktop\Tickets\ict-ticketting` (fetch and pull, without overwriting uncommitted local work) so it matches GitHub.

# Product brief

IT Service Ticketing web application built with Laravel.

### Product summary
Users log in and submit IT/tech service requests (tickets). Admins see all
requests and assign them to technicians as tasks. Technicians work their
assigned tasks and update status. Admins can see when tasks are started and
completed. After a ticket is resolved, the requesting user can leave a
satisfaction review (1-5 stars + comment), which admins can view.

### Roles (stored as an enum column on users: user, technician, admin)
- user: register-free (accounts are created by admin or seeder), log in/out,
  reset forgotten password, change password, create and track OWN tickets,
  comment on own tickets, review own resolved tickets.
- technician: sees ONLY tickets assigned to them, updates status, adds notes.
- admin: sees everything, manages users, assigns/reassigns tickets, views
  reviews and reports.

### Tech stack
- Latest stable Laravel and PHP 8.3+ (use whatever composer installs; tell me
  the versions), MySQL 8, Blade + Tailwind CSS via Laravel Breeze (Blade stack).
  No React/Vue/Livewire, keep it simple.
- Pest or PHPUnit feature tests (use PHPUnit if Breeze installs it).

### Data model (final; do not invent extra tables without asking)
- users: id, name, email (unique), password, role (enum: user|technician|admin,
  default user), phone (nullable), is_active (bool, default true), timestamps,
  remember_token, email_verified_at
- categories: id, name (unique), is_active
- tickets: id, reference (unique, e.g. TKT-000123), user_id (FK requester),
  category_id (FK), title, description, priority (enum: low|medium|high|urgent),
  status (enum: open|assigned|in_progress|resolved|closed),
  assigned_to (nullable FK users), assigned_by (nullable FK users),
  assigned_at, started_at, resolved_at, closed_at (all nullable), timestamps
- ticket_comments: id, ticket_id, user_id, body, timestamps
- ticket_activities: id, ticket_id, user_id (nullable), action (string),
  from_status (nullable), to_status (nullable), note (nullable), created_at
  (audit trail of every status change and assignment)
- reviews: id, ticket_id (UNIQUE FK, one review per ticket), user_id,
  rating (tinyint 1-5), comment (nullable), timestamps
Add proper foreign keys, indexes on tickets(status), tickets(user_id),
tickets(assigned_to), and sensible onDelete rules (restrict on users/tickets;
never cascade-delete ticket history by accident).

### Status workflow (enforce in ONE place, a backed enum with allowed transitions)
open -> assigned -> in_progress -> resolved -> closed
Rules: only admin assigns/reassigns; only the assigned technician (or admin)
moves assigned->in_progress->resolved; user can close a resolved ticket or
the admin can; reviews only allowed when status is resolved or closed and
only by the ticket owner. Invalid transitions must be rejected with a clear
error, not silently ignored.

### Engineering rules (follow in every phase)
1. Use Eloquent, migrations, factories and seeders. No raw SQL with user input.
2. Validation lives in Form Request classes. Authorization lives in Policies
   (TicketPolicy, ReviewPolicy, UserPolicy) plus a simple EnsureRole middleware
   for route groups. Never rely on hidden form fields or hidden buttons for
   security.
3. Keep controllers thin. Put status/assignment logic in a service class or
   model methods (e.g. TicketService), wrapped in DB::transaction and writing
   a ticket_activities row.
4. Use enums (PHP backed enums) for role, status, priority; cast them on models.
5. Prevent N+1 queries: eager load relations; paginate every list (15/page).
6. Use route model binding, named routes, and route groups by role.
7. Use CSRF, mass-assignment protection ($fillable), password hashing, and
   never expose other users' tickets (test this).
8. Escape output (Blade {{ }}), no {!! !!} on user input.
9. Show flash messages for success/error; show validation errors inline.
10. Use .env for all config; never hardcode secrets. Mail driver = log in dev.
11. Write at least one feature test per behaviour that matters (auth, role
    access, ticket creation, assignment, transitions, review rules).
12. Prefer the simplest solution. No unnecessary packages. If you want to add
    a package, ask me first.

### How each phase is delivered
- Build the app ONE PHASE AT A TIME. Do not build ahead of the current phase.
- Restate the phase goal in 2-3 lines and list the files to create/change.
- Implement the phase directly in this repository and open one pull request per phase.
- Run the checks yourself (`php artisan migrate:fresh --seed`, `php artisan test`, `php artisan route:list`) and report the exact results.
- End the PR description with a "Verify" checklist: the commands above plus manual click-through steps, with the expected result of each.
- If something is ambiguous, ask before coding. When fixing a failure, do not rewrite unrelated files.
- Stop after the phase. Do not start the next one.
