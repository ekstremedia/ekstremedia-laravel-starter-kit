# Per-tenant migrations

Migrations placed in this directory run **inside each tenant's Postgres schema** (`tenant1`, `tenant2`, …) when a tenant is created (`TenantCreated` → `Jobs\MigrateDatabase`) and on every `php artisan tenants:migrate`.

Use this folder for tables that are per-tenant business data.

**Don't put here:** `users`, `sessions`, `password_reset_tokens`, `personal_access_tokens`, Spatie permission tables, `tenants`, `tenant_user`, `jobs`, `cache`, `pulse_*`, `notifications`, `media`, `activity_log`, settings tables. Those live in `public` (central) so login, RBAC and audit logging are shared across tenants.

**Do put here:** anything a customer owns — orders, products, projects, documents, etc.
