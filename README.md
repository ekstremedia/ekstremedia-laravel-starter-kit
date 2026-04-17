# Laravel Starter Kit

Opinionated, batteries-included Laravel starter kit. Everything you reach for in most projects is already wired: auth, admin dashboard, queues, broadcasting, media uploads, activity log, observability, backups, impersonation, notifications, CI, and a full test suite.

## Included

### Core

- **Laravel 13** + **PHP 8.4** (Imagick + GD with JPEG/WebP + phpredis installed in the image)
- **Inertia.js v3** + **Vue 3** + **TypeScript**
- **Tailwind CSS v4** + **PrimeVue v4** + **primeicons** + **GSAP**
- **PostgreSQL 17** + **Redis 7** (cache, queue, session) + **Mailpit**
- **Docker** stack with Supervisor running php-fpm, nginx, Vite, **Reverb**, **Horizon**, **Pulse check/work**, and the **Laravel scheduler**

### Auth & users

- **Laravel Fortify** (login, register, email verification, password reset, TOTP 2FA + recovery codes) + **Sanctum** (SPA session + API tokens)
- **Spatie Permission** — roles/permissions seeded (`Admin` / `Editor` / `User`)
- **Spatie Medialibrary** — profile-photo uploads with synchronous `thumb` (64×64) + queued `avatar` (256×256) WebP conversions; easy to switch disks via `MEDIA_DISK`
- **Impersonation** (lab404/laravel-impersonate) — one-click "log in as user" for admins, amber banner + activity-log entries on start/stop
- **Dev easy-login** button on the login page when `DEV_EASY_LOGIN_ENABLED=true`

### Admin dashboard (`/admin`, role:Admin gated)

- Users / Roles / Permissions CRUD with form requests and validation
- Activity log viewer (filter by user, date, log name, event)
- Server & System page: live queue / Reverb / Redis pings + PHP / Laravel / drivers / cache / extensions snapshot
- Mail settings (encrypted SMTP password, applied to runtime config) + test-send button
- Backups — scheduled daily (01:30 clean / 02:00 run / 06:00 monitor) via spatie/laravel-backup; manual run/clean buttons
- Horizon (`/horizon`), Pulse (`/pulse`), Log viewer (`/log-viewer`) — each gated to admins

### Observability & reliability

- **Sentry** (`sentry/sentry-laravel`) — enable by setting `SENTRY_LARAVEL_DSN`
- **Laravel Pulse** — app metrics dashboard
- **Laravel Horizon** — queue dashboard with configurable workers
- **Spatie Activitylog** — user changes auto-logged; custom viewer in admin
- **opcodesio/log-viewer** — raw `storage/logs/*` browsable at `/log-viewer`
- **Notifications inbox** — DB-backed, unread count shared via Inertia, bell with badge in nav, mark-read/mark-all-read/delete endpoints

### Frontend polish

- Dark-first UI with a custom `--color-dark-*` palette; one-click light/dark toggle
- Single-flag `LanguageSwitcher` dropdown with English + Norwegian translations
- Shared user preferences (`locale`, `dark_mode`) exposed as `user_settings` via debounced `PATCH /settings`
- PrimeVue Toast + ConfirmDialog + DataTable already wired with blue-tinted dark-mode styling
- **Mobile-ready** — admin sidebar collapses behind a hamburger + backdrop, top nav compacts the user/lang controls into a dropdown, every DataTable ships with `scrollable` for horizontal overflow, grid layouts stack at the `sm` breakpoint

### Code quality & testing

- **Pest 4.6** backend suite (184 tests, 651 assertions) — auth, CRUD, health, mail, avatars, impersonation, notifications, backups, arch rules
- **Vitest 4** frontend suite (18 tests) for `TextInput`, `PrimaryButton`, `DarkModeToggle`, `LanguageSwitcher`
- **Larastan** (phpstan.neon, level 5) — `make stan`
- **Laravel Pint** — `make pint`
- **Husky + lint-staged** pre-commit hook runs Pint on staged PHP and `vue-tsc` on staged TS/Vue
- **GitHub Actions** pipeline: parallel backend (Pest + Pint + Larastan) and frontend (Vitest + tsc + build) jobs, Postgres + Redis service containers
- **Laravel Boost** MCP server pre-configured (`.mcp.json`) to run inside the container

## New Project Flow

### 1. Create a repo from this template

Use GitHub's template-repo flow or clone/copy this repository into a new project folder.

### 2. Initialize your local `.env`

```bash
make init
```

The init script creates `.env` if it does not exist, prompts for your app name, URL, database credentials, and seeded admin account, then writes the correct values for Docker, Vite, mail, and the starter seeder.

### 3. Start the stack

```bash
make build
```

### 4. Add your local hostname

Point your chosen hostname from `.env` to the container IP in `/etc/hosts`, for example:

```txt
192.168.x.x   starter-kit.test
```

### 5. Visit the app

- App: value from `APP_URL` (default host port `8120`, configurable via `APP_HOST_PORT`)
- Mailpit: `http://localhost:${MAILPIT_HOST_PORT:-8126}`
- Reverb: `ws://<your-app-host>:8080`

Sanctum stateful domains are read from `SANCTUM_STATEFUL_DOMAINS` in `.env` — set this to your `APP_URL` host (and any additional domains the SPA is served from).

## Seeded Admin

The database seeder creates a verified admin user from these `.env` values:

- `STARTER_ADMIN_FIRST_NAME`
- `STARTER_ADMIN_LAST_NAME`
- `STARTER_ADMIN_EMAIL`
- `STARTER_ADMIN_PASSWORD`

If `DEV_EASY_LOGIN_ENABLED=true`, the login page shows a local-only shortcut button that logs in as user `1`. This is gated to local runtime and test runtime only.

## Authentication

Authentication is powered by [Laravel Fortify](https://laravel.com/docs/fortify) (headless backend) and [Laravel Sanctum](https://laravel.com/docs/sanctum) (SPA session auth + API tokens).

**Included flows:** login, registration, email verification, password reset (forgot password), and two-factor authentication (TOTP + recovery codes).

Fortify actions live in `app/Actions/Fortify/` and custom response classes in `app/Http/Responses/`. Views are rendered as Inertia pages via `FortifyServiceProvider`.

### Two-Factor Authentication

Users can enable 2FA through the Fortify endpoints:

- `POST /user/two-factor-authentication` — enable (requires password confirmation)
- `POST /user/confirmed-two-factor-authentication` — confirm setup with TOTP code
- `GET /user/two-factor-qr-code` — retrieve QR code SVG
- `GET /user/two-factor-recovery-codes` — retrieve recovery codes
- `DELETE /user/two-factor-authentication` — disable

When a user with 2FA enabled logs in, they are redirected to `/two-factor-challenge` to enter their TOTP code or a recovery code.

After login, verified users land on `/dashboard` (account overview cards) and can self-manage profile, password, and 2FA at `/profile`. Both routes are gated by `auth` + `verified` middleware in `routes/web.php`.

## Roles & Seeded Users

`RoleAndPermissionSeeder` creates three roles:

- **Admin** — full access (all permissions)
- **Editor** — `view dashboard`, `manage resources`, `manage settings`, `manage profile`
- **User** — `view dashboard`, `manage settings`, `manage profile`

> **Note:** Permission names like `manage resources` are example placeholders. Rename or replace them to match your domain before shipping.

`DatabaseSeeder` creates the configured admin (from `STARTER_ADMIN_*` env vars) plus a small demo set of editors and users (only when `SEED_DEMO_USERS=true`). Roles and permissions are shared as Inertia props on `auth.user.roles` / `auth.user.permissions`.

## Commands

```bash
make init               # Prompt for app-specific .env values
make build              # Build and start containers
make up                 # Start containers
make down               # Stop containers
make restart            # Restart containers
make destroy            # Stop containers and remove volumes (local only)

make test               # Run Pest tests in the app container
make fresh              # Fresh migrate and seed (local only)
make rebuild            # Reset DB to a clean slate: drop tenant schemas + migrate:fresh --seed + clear caches (local only)
make migrate            # Run migrations
make seed               # Run seeders
make rollback           # Roll back last migration

make shell              # Open a shell in the app container
make tinker             # Open Laravel Tinker
make db-shell           # Open PostgreSQL shell
make logs               # Tail app logs
make logs-all           # Tail all container logs
make reverb-restart     # Restart the Reverb websocket server

make pint               # Run Laravel Pint
make stan               # Run Larastan static analysis
make test-js            # Run Vitest frontend tests
make test-all           # Run Pest + typecheck + Vitest
make backup             # Trigger a manual backup
make backup-clean       # Drop old backups
make cache-clear        # Clear Laravel caches
make composer-install   # Run composer install in container
make npm-install        # Run npm install in container
make npm-build          # Build frontend assets
```

`make help` lists every target with a description.

> **Destructive targets** (`destroy`, `fresh`, `rebuild`) refuse to run unless your `.env` has `APP_ENV=local`. This is a belt-and-braces guard so you can't wipe a staging/prod DB by muscle-memory. If you really need to reset a non-local clone, flip `APP_ENV=local` in `.env` first.

## Admin Surface

All admin pages live under `/admin/*` and are gated by the `role:Admin` middleware.

| Route | Purpose |
| --- | --- |
| `/admin` | Overview tiles |
| `/admin/users` | User CRUD + role assignment + impersonate |
| `/admin/roles` | Role CRUD + permission sync |
| `/admin/permissions` | Permissions registry |
| `/admin/activity` | Activity log viewer (filter by user/date/log name/event) |
| `/admin/mail` | SMTP settings + test send |
| `/admin/backups` | List + trigger backups |
| `/admin/system` | Live queue/Reverb/Redis health + full runtime snapshot |
| `/horizon` | Laravel Horizon dashboard |
| `/pulse` | Laravel Pulse metrics |
| `/log-viewer` | Raw `storage/logs/*` viewer |

Impersonation writes a yellow banner across the top while active; clicking **Stop impersonating** returns you to the original admin account.

## Multi-customer (optional)

`stancl/tenancy` v3 is pre-wired but **disabled by default**. Clone the kit, run `make init && make build && make migrate`, and you get a plain single-tenant Laravel SPA (`/dashboard`, `/profile`, no `/c/...` prefix, no landlord UI).

To turn it on, set `TENANCY_ENABLED=true` in `.env`, then:

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan migrate:fresh --seed
```

When enabled, the app routes users through `/c/{slug}/dashboard`, seeds a `default` customer, exposes `/admin/customers` for CRUD + member management, and provisions a dedicated Postgres schema (`tenant<id>`) per customer. Same code path, same auth, same Admin role — it's a pure add-on.

**Vocabulary note:** the word "Customer" is what shows up in URLs, admin UI, and controllers. Underneath we still lean on the `stancl/tenancy` package, so the Eloquent model is `App\Models\Tenant`, the DB tables are `tenants` / `tenant_user`, and the config file is `config/tenancy.php`. That boundary is intentional — the user-visible layer is Customer, the package-facing plumbing stays Tenant.

When disabled, the `tenants` and `tenant_user` tables still exist but stay empty, so flipping the flag on later requires no migration. See `AGENTS.md` → "Multi-tenancy" for the architecture details.

## Table prefix (optional)

Every migration in this kit runs through Laravel's Schema builder / Eloquent, so they inherit the connection-level `prefix` from `config/database.php`. Set `DB_TABLE_PREFIX=acme_` (trailing separator included) in `.env`, run `php artisan migrate:fresh`, and you get `acme_users`, `acme_roles`, `acme_tenants`, etc. — all 26 core tables, including Spatie Permission, Pulse, Media Library, Activity Log, Fortify/Sanctum, jobs/cache, and the tenancy tables.

Good when cohabiting a database with other apps and you want every table under one recognisable namespace. Skip it if you use a dedicated DB per app (cleaner). **Caveat**: only queries that go through Eloquent / Query Builder / Schema inherit the prefix — raw SQL (`DB::select('SELECT ... FROM users')`) must call `DB::getTablePrefix()` manually. External tooling (pgAdmin, Metabase, dashboard screenshots, one-off SQL scripts) now sees the prefixed names too.

## Key Env Vars

Beyond standard Laravel vars, the starter kit uses:

```dotenv
# Admin seeded by DatabaseSeeder + UserSeeder
STARTER_ADMIN_EMAIL=admin@example.test
STARTER_ADMIN_PASSWORD=password
SEED_DEMO_USERS=true              # Seed 3 editors + 8 users with nb_NO fake names

# Easy login shortcut shown on /login in local/testing
DEV_EASY_LOGIN_ENABLED=false

# Spatie Medialibrary
MEDIA_DISK=public                 # switch to 's3' (or any disk) to move uploads
IMAGE_DRIVER=imagick              # fallback: 'gd'
QUEUE_CONVERSIONS_BY_DEFAULT=true

# Sentry (leave DSN empty to disable)
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.2
SENTRY_PROFILES_SAMPLE_RATE=0.2

# Laravel Pulse cache store — database avoids phpredis/Collection unserialize
# issues when the main CACHE_STORE is redis.
PULSE_CACHE_DRIVER=database

# Log viewer (opcodesio) — list every host the SPA might be served from
LOG_VIEWER_API_STATEFUL_DOMAINS=starter-kit.test,localhost

# Table prefix applied to every migration + query (empty = stock names)
DB_TABLE_PREFIX=

# Multi-customer (off by default — see "Multi-customer (optional)" above)
TENANCY_ENABLED=false
TENANCY_DEFAULT_CUSTOMER=default
```

## Testing

Two test suites run in parallel in CI and locally.

**Backend (Pest 4):**

```bash
make test                # all suites
docker compose exec app php artisan test --compact
docker compose exec app vendor/bin/phpstan analyse
docker compose exec app vendor/bin/pint --test
```

Pest uses SQLite `:memory:` by default (forced via `phpunit.xml`). Broadcasting, Pulse, and Nightwatch are disabled in the test env so Reverb is not required.

**Frontend (Vitest 4 + Vue Test Utils):**

```bash
docker compose exec app npm test
docker compose exec app npm run typecheck
```

Component specs live in `tests/frontend/Components/`. The `tests/frontend/setup.ts` file stubs Inertia so mounted components don't need a real SPA.

**Everything at once:**

```bash
make test-all
```

GitHub Actions (`.github/workflows/tests.yml`) runs two parallel jobs — **Backend** (Postgres + Redis service containers, Pint, Larastan, Pest) and **Frontend** (typecheck, Vitest, build).

## What To Customize First

- Replace the welcome page copy in [`resources/js/i18n/en.ts`](./resources/js/i18n/en.ts) and [`resources/js/i18n/no.ts`](./resources/js/i18n/no.ts)
- Adjust permissions in [`database/seeders/RoleAndPermissionSeeder.php`](./database/seeders/RoleAndPermissionSeeder.php)
- Add your first domain models and pages
- Update branding and favicon
- Tighten the auth flow if your product needs invitations, teams, or social login
