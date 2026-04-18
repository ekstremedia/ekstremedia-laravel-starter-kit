# Per-customer migrations

Migrations placed in this directory run **inside each customer's Postgres schema** (`tenant1`, `tenant2`, …) when a customer row is created (`TenantCreated` → `Jobs\MigrateDatabase`) and on every `php artisan tenants:migrate`.

Use this folder for tables that are per-customer business data.

**Don't put here:** `users`, `sessions`, `password_reset_tokens`, `personal_access_tokens`, Spatie permission tables, `tenants`, `tenant_user`, `jobs`, `cache`, `pulse_*`, `notifications`, `media`, `activity_log`, settings tables. Those live in `public` (central) so login, RBAC and audit logging are shared across customers.

**Do put here:** anything a customer owns — orders, products, projects, documents, etc.

> **Folder name.** Keep this directory called `tenant/` — stancl/tenancy's `MigrateDatabase` job reads the path from `config('tenancy.migration_parameters.--path')` which points at `database_path('migrations/tenant')`. If you rename the folder, update that config key to match.

## What runs where during tests

`php artisan test` uses SQLite in-memory (see `phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) with `TENANCY_ENABLED=true`. `tests/Pest.php` strips stancl's tenancy bootstrappers and unbinds the `TenantCreated`/`TenantDeleted` listeners so creating a customer just writes a `tenants` row — it never runs migrations inside a per-customer SQLite file. In other words:

- **Production / dev (Postgres):** creating a customer → `CREATE SCHEMA tenant<id>` → runs everything under `database/migrations/tenant/` inside that schema.
- **Feature tests (SQLite):** creating a customer → writes a `tenants` row, nothing else. Any migrations in this folder are **never applied** to the test DB, so tests can't rely on per-customer tables living anywhere real. Exercise business-model migrations in a dedicated integration suite against the Postgres dev stack.
