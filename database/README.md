# Database — Campus Lost-and-Found Portal

This directory contains the PostgreSQL database scripts for the Campus Lost-and-Found Portal. The application stack remains HTML, CSS, JavaScript, jQuery, PHP, PostgreSQL, and Apache.

## Files

- `schema.sql` — creates the `users`, `reports`, and `claims` tables, constraints, and triggers.
- `indexes.sql` — adds indexes for common report and claim lookups.
- `seed.sql` — optional development-only demo accounts and sample reports.

## Database creation and execution order

Create a database first, for example named `campus_lost_found`, then execute these scripts in order:

1. `schema.sql`
2. `indexes.sql`
3. `seed.sql` (optional; development only)

Using the PostgreSQL command-line client (`psql`), from the repository root:

```bash
createdb campus_lost_found
psql -d campus_lost_found -v ON_ERROR_STOP=1 -f database/schema.sql
psql -d campus_lost_found -v ON_ERROR_STOP=1 -f database/indexes.sql
psql -d campus_lost_found -v ON_ERROR_STOP=1 -f database/seed.sql
```

If your PostgreSQL installation requires a database username, add `-U your_database_user` to the `psql` commands and use the appropriate connection settings. Do not put database passwords in source files or commit them to GitHub.

## Tables and relationships

### `users`

Stores user accounts, unique email addresses, password hashes, and the `user` or `admin` role. PHP should store passwords using `password_hash()` and verify them using `password_verify()`; never store plaintext passwords.

### `reports`

Stores lost/found item reports, including the report owner, item name, type, category, location, date, description, optional image path, and status. A report belongs to one user.

Allowed report types: `lost`, `found`.

Allowed report statuses: `open`, `claim_requested`, `resolved`, `closed`.

### `claims`

Stores claims against found-item reports, the claimant, the claim message, review status, and optional reviewer. A user can have at most one claim record per report; a rejected or withdrawn claim is retained as history, so a new claim for the same report requires an explicit application/database policy change.

Allowed claim statuses: `pending`, `approved`, `rejected`, `withdrawn`.

The schema prevents claims against lost reports and prevents users from claiming their own reports. The application must still enforce authentication, authorization, valid status transitions, and claim-review permissions.

## Demo data warning

The optional seed script creates `student@example.test` and `admin@example.test` with the demo password `password`. These are strictly for local development. Do not use them in a deployed environment. Remove demo records and configure real accounts before any public deployment.

## PHP connection

Use PDO with the PostgreSQL driver (`pdo_pgsql`) or PHP's PostgreSQL extension. Use parameterized queries for all user-provided values. Keep credentials in local configuration/environment settings, not in tracked source files.

## Notes

- Scripts use PostgreSQL features such as identity columns, `plpgsql` triggers, and `CREATE INDEX IF NOT EXISTS`.
- Run the schema against a new project database first. It is not a versioned migration system and should not be treated as a production migration for an existing database.
- Uploaded images should be validated by PHP, stored with safe generated filenames, and never trusted based only on the original client filename.
