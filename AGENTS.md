# Laravel Starter Kit

An opinionated starter repository for new Laravel products. The goal is to avoid redoing the same baseline setup on every project while still keeping the code generic and easy to reshape for a real domain.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.4, PostgreSQL 17, Redis 7
- **Frontend:** Vue 3 + TypeScript, Inertia.js (SPA mode), Tailwind CSS v4, PrimeVue v4
- **Testing:** Pest
- **Other:** Docker, Mailpit, GSAP, vue-i18n, Spatie Laravel Permission, Laravel Reverb

## Core Principle

This repository is a reusable foundation, not an app-specific product. Keep starter behavior generic. Avoid baking domain-specific nouns, UI copy, permissions, or seed data into the base unless they are clearly intended as placeholders.

## Architecture

This is an Inertia SPA. Laravel handles routing, middleware, controllers, auth, settings persistence, and seeders. Vue renders the UI from `resources/js/Pages`.

### Key Directories

```
app/
  Http/
    Controllers/
      Auth/              # RegisterController, LoginController, EmailVerificationController
      SettingsController # PATCH /settings — partial updates to user settings
    Middleware/          # HandleInertiaRequests shares auth, settings, locale, debug, flash
  Models/
    User.php             # implements MustVerifyEmail, HasRoles, has settings() helper
    UserSetting.php      # single JSONB settings column, defaults defined in $defaults
config/
  dev.php                # local-only development flags such as easy login
resources/js/
  Pages/
    Welcome.vue          # generic starter landing page
    Auth/                # Login.vue, Register.vue, VerifyEmail.vue
  Layouts/
    AppLayout.vue        # top nav, login/register links, locale switcher, dark mode
    AuthLayout.vue       # auth page shell
  Components/
    DarkModeToggle.vue
    LanguageSwitcher.vue
    PrimaryButton.vue
    TextInput.vue
  composables/
    useSettings.ts       # localStorage + server sync for user settings
    useDarkMode.ts       # theme integration
    useLocale.ts         # vue-i18n integration
  i18n/
    en.ts
    no.ts
database/
  seeders/
    DatabaseSeeder.php           # generic seeded admin from .env
    RoleAndPermissionSeeder.php  # starter permissions and roles
docker/
  entrypoint.sh
  nginx.conf
  supervisord.conf       # runs php-fpm, nginx, vite, reverb
scripts/
  init-starter.sh       # interactive project bootstrap for .env
tests/
  Feature/
    Auth/
    SettingsTest.php
```

## Project Setup

Use the starter bootstrap flow:

```bash
make init
make build
```

`make init` creates `.env` from `.env.example` and prompts for:

- app name
- app URL / local hostname
- database name, user, password
- seeded admin account
- local easy-login toggle

## Settings System

User preferences live in `user_settings.settings` as JSON. Current starter settings:

- `locale`
- `dark_mode`

Flow:

1. localStorage is read immediately
2. authenticated Inertia props override on login or page change
3. updates write to localStorage immediately
4. authenticated users sync to the database through debounced `PATCH /settings`

For Inertia requests, settings updates return an Inertia-safe redirect. For JSON requests, they return JSON.

## Localization

The starter supports English (`en`) and Norwegian (`no`). Always update both translation files when adding user-facing text.

The localStorage key is controlled by `VITE_APP_STORAGE_KEY` so each generated app can keep its own browser settings namespace.

## Roles & Permissions

Seeded roles:

- `Admin`
- `User`

Starter permissions:

- `view dashboard`
- `manage users`
- `manage roles`
- `manage settings`
- `manage profile`

These are starter defaults. They should usually be adapted early in a real project.

## Local Dev Login

If `DEV_EASY_LOGIN_ENABLED=true`, the login page shows a shortcut button that posts to `POST /login/dev` and logs in as user `1`.

Safety rules:

- it is only exposed in local runtime
- it is also allowed in tests
- it must never be enabled or depended on in production

## Testing

Tests are designed to work both on the host and inside Docker.

- `.env.testing` contains the testing baseline
- `phpunit.xml` forces critical test env vars so Docker-exported values cannot override them

Use:

```bash
php artisan test
docker compose exec app php artisan test
```

## Websockets

Laravel Reverb is included and runs in the app container under Supervisor.

- `BROADCAST_CONNECTION=reverb` in local `.env`
- `REVERB_HOST=127.0.0.1` is used for server-side publishing inside the container
- `VITE_REVERB_HOST` should stay on the public local hostname so the browser can connect to `ws://<host>:8080`
- `.env.testing` keeps broadcasting disabled so tests do not depend on a live websocket server

## Starter Maintenance Rules

- Keep docs, copy, and defaults generic
- Prefer environment-driven configuration over hardcoded project names
- If you add a new user-facing string, update both `en.ts` and `no.ts`
- If you add a new reusable setting, update `UserSetting::$defaults` and the `UserSettings` TypeScript interface
- When changing Docker or test env behavior, verify both host and container test runs
