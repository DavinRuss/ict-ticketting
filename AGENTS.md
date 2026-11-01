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
