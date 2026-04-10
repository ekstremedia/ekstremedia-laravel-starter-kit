# Laravel Starter Kit

Opinionated Laravel starter kit for new product work. It ships with the baseline stack and repeated setup already done: Inertia SPA mode, Vue 3 + TypeScript, Tailwind CSS v4, PrimeVue v4, Laravel Reverb, Pest, Docker, Spatie roles/permissions, localization, dark mode, shared user settings, and a polished auth flow.

## Included

- Laravel 13 + PHP 8.4
- Inertia.js + Vue 3 + TypeScript
- Tailwind CSS v4 + PrimeVue v4
- PostgreSQL 17 + Redis 7
- Pest test suite + GitHub Actions
- Laravel Fortify authentication (login, registration, email verification, password reset, two-factor authentication)
- Laravel Sanctum for SPA session auth and API tokens
- Spatie roles/permissions seeded with `Admin`, `Editor`, and `User` plus a richer demo user set
- Authenticated dashboard and self-service profile page (name, email, password, 2FA management)
- Dark-first UI with a dark-blue palette (`--color-dark-*` in `resources/css/app.css`) and PrimeVue toast notifications wired through `useFlashToast`
- Shared user settings for `locale` and `dark_mode` (defaults to dark)
- English and Norwegian translations
- Local-only easy login for development
- Docker app stack with Nginx, PHP-FPM, Vite, PostgreSQL, Redis, and Mailpit
- Laravel Reverb websocket server running inside the app container

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
make destroy            # Stop containers and remove volumes

make test               # Run Pest tests in the app container
make fresh              # Fresh migrate and seed
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
make cache-clear        # Clear Laravel caches
make composer-install   # Run composer install in container
make npm-install        # Run npm install in container
make npm-build          # Build frontend assets
```

## Testing

Tests use [`.env.testing`](./.env.testing) plus forced `phpunit.xml` environment overrides so Docker-exported env vars cannot leak into the test environment. The test environment keeps broadcasting disabled, so Reverb is not required for PHP test runs.

These should both pass:

```bash
php artisan test
docker compose exec app php artisan test
```

## What To Customize First

- Replace the welcome page copy in [`resources/js/i18n/en.ts`](./resources/js/i18n/en.ts) and [`resources/js/i18n/no.ts`](./resources/js/i18n/no.ts)
- Adjust permissions in [`database/seeders/RoleAndPermissionSeeder.php`](./database/seeders/RoleAndPermissionSeeder.php)
- Add your first domain models and pages
- Update branding and favicon
- Tighten the auth flow if your product needs invitations, teams, or social login
