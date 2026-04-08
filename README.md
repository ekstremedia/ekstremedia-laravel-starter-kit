# Laravel Starter Kit

Opinionated Laravel starter kit for new product work. It ships with the baseline stack and repeated setup already done: Inertia SPA mode, Vue 3 + TypeScript, Tailwind CSS v4, PrimeVue v4, Laravel Reverb, Pest, Docker, Spatie roles/permissions, localization, dark mode, shared user settings, and a polished auth flow.

## Included

- Laravel 13 + PHP 8.4
- Inertia.js + Vue 3 + TypeScript
- Tailwind CSS v4 + PrimeVue v4
- PostgreSQL 17 + Redis 7
- Pest test suite + GitHub Actions
- Registration, login, logout, email verification
- Spatie roles/permissions with seeded `Admin` and `User`
- Shared user settings for `locale` and `dark_mode`
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

- App: value from `APP_URL`
- Mailpit: `http://localhost:${MAILPIT_HOST_PORT:-8126}`
- Reverb: `ws://<your-app-host>:8080`

## Seeded Admin

The database seeder creates a verified admin user from these `.env` values:

- `STARTER_ADMIN_FIRST_NAME`
- `STARTER_ADMIN_LAST_NAME`
- `STARTER_ADMIN_EMAIL`
- `STARTER_ADMIN_PASSWORD`

If `DEV_EASY_LOGIN_ENABLED=true`, the login page shows a local-only shortcut button that logs in as user `1`. This is gated to local runtime and test runtime only.

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
# laravel-starter-kit
