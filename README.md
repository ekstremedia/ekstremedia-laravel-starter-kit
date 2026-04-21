# Laravel Starter Kit

A batteries-included Laravel 13 + Inertia/Vue starter, so you can skip the yak-shaving on day one.

Everything you usually bolt on to a fresh Laravel app is already wired up: auth with 2FA, an admin dashboard, roles & permissions, queues, broadcasting, media uploads, activity log, backups, observability, notifications, impersonation, Docker, and a full test suite.

## Stack

- **Laravel 13 · PHP 8.4** · PostgreSQL 17 · Redis 7 · Mailpit
- **Inertia.js v3 + Vue 3 + TypeScript** · Tailwind v4 · PrimeVue v4
- **Docker** with php-fpm, nginx, Vite, Reverb, Horizon, Pulse, and the scheduler all running under supervisor
- **Fortify** (login, register, email verification, password reset, TOTP 2FA + recovery codes) · **Sanctum** · **Spatie Permission** (`Admin` / `Editor` / `User` seeded)
- **Spatie** Medialibrary · Activitylog · Backup · Laravel Pulse · Horizon · Sentry · opcodesio log-viewer · lab404 impersonate
- **Pest 4** backend tests · **Vitest 4** frontend tests · Pint · Larastan · Husky pre-commit · GitHub Actions CI

## Quick start

```bash
make init     # creates .env, prompts for app name, URL, DB creds, seeded admin
make build    # builds and starts the Docker stack
```

Point your hostname at the container in `/etc/hosts`, then open `APP_URL`. Mailpit is at `http://localhost:${MAILPIT_HOST_PORT:-8126}`.

Log in with the admin you seeded, or flip on `DEV_EASY_LOGIN_ENABLED=true` and click the local-only shortcut button on `/login`.

`make help` lists every Make target. The ones you'll actually use:

```bash
make shell           # drop into the app container
make test            # Pest
make test-all        # Pest + Larastan + typecheck + Vitest
make fresh           # migrate:fresh --seed (local only)
make logs            # tail app logs
```

Destructive targets (`destroy`, `fresh`, `rebuild`) refuse to run unless `APP_ENV=local`.

## Admin

Everything at `/admin/*` is gated by `role:Admin`.

| Route | What's there |
| --- | --- |
| `/admin` | Live dashboard — stats, charts, recent activity, quick links |
| `/admin/users` | Users CRUD, role assignment, quotas, impersonate |
| `/admin/customers` | Customer (tenant) CRUD — only when `TENANCY_ENABLED=true` |
| `/admin/roles` · `/admin/permissions` | Roles + granular permissions |
| `/admin/mail` | SMTP settings (encrypted) + test send |
| `/admin/storage` | Per-user storage usage and quotas |
| `/admin/backups` | Run/clean backups · download archives · prepare restores |
| `/admin/system` | Queue / Reverb / Redis pings + runtime snapshot |
| `/admin/monitoring` | Activity log + embedded Horizon · Pulse · log-viewer tabs |

Impersonation shows an amber banner while active; click **Stop impersonating** to return.

## Multi-customer (optional)

`stancl/tenancy` v3 is pre-wired but off by default — ship single-tenant out of the box. Flip `TENANCY_ENABLED=true` and `php artisan migrate:fresh --seed`, and you get `/c/{slug}/*` URLs, a `default` customer, `/admin/customers` CRUD, and a dedicated Postgres schema per customer.

The user-visible name is **Customer**. Under the hood the package model stays `App\Models\Tenant` — the boundary is intentional. Full notes in `AGENTS.md`.

## Table prefix (optional)

Set `DB_TABLE_PREFIX=acme_`, run `migrate:fresh`, and every core table is namespaced (`acme_users`, `acme_tenants`, …). Only queries that go through Eloquent / Query Builder / Schema inherit the prefix — raw SQL must call `DB::getTablePrefix()` itself.

## Auth & 2FA

Login, register, email verify, forgot password, TOTP 2FA, recovery codes. All backed by Fortify actions in `app/Actions/Fortify/` and custom response classes in `app/Http/Responses/`. Views are Inertia pages registered by `FortifyServiceProvider`. When a 2FA user logs in they're redirected to `/two-factor-challenge`.

Users self-manage profile, password, and 2FA at `/profile`.

## Testing

```bash
make test         # Pest (SQLite :memory: — no Reverb needed)
make test-all     # Pest + Larastan + vue-tsc + Vitest
```

Pest lives in `tests/`, Vitest in `tests/frontend/`. CI (`.github/workflows/tests.yml`) runs both suites in parallel with Postgres + Redis service containers.

## Customize first

- Welcome page copy — `resources/js/i18n/{en,no}.ts`, keys under `welcome.*`
- Permissions — `database/seeders/RoleAndPermissionSeeder.php`
- Branding + favicon — `public/`, `resources/js/Pages/Welcome.vue`, `.env`'s `APP_NAME`
- Any env vars you'll want to review — see `.env.example` (every setting is commented)
