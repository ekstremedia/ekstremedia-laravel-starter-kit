# Laravel Starter Kit

An opinionated starter repository for new Laravel products. The goal is to avoid redoing the same baseline setup on every project while still keeping the code generic and easy to reshape for a real domain.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.4, PostgreSQL 17, Redis 7
- **Auth:** Laravel Fortify (headless backend) + Laravel Sanctum (SPA session + API tokens)
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
  Actions/
    Fortify/             # CreateNewUser, UpdateUserProfileInformation, UpdateUserPassword,
                         # ResetUserPassword, PasswordValidationRules — Fortify action overrides
  Http/
    Controllers/
      Auth/
        DevLoginController.php # POST /login/dev — local easy login (only auth controller left;
                               # login/register/verify are all handled by Fortify)
      SettingsController # PATCH /settings — partial updates to user settings
    Middleware/          # HandleInertiaRequests shares auth (with roles+permissions),
                         # settings, locale, debug, flash (incl. Fortify status)
    Responses/
      LoginResponse.php       # redirects to /dashboard (or /email/verify if unverified)
      RegisterResponse.php    # redirects to /email/verify after registration
  Models/
    User.php             # implements MustVerifyEmail, HasRoles, TwoFactorAuthenticatable,
                         # first_name/last_name schema, settings() helper
    UserSetting.php      # single JSONB settings column, defaults defined in $defaults
                         # (dark_mode defaults to true — dark-first)
  Providers/
    FortifyServiceProvider.php # binds Fortify views to Inertia pages, registers actions
                               # and custom Login/Register response classes
config/
  dev.php                # local-only development flags such as easy login
  fortify.php            # features enabled, home → /dashboard
  sanctum.php            # stateful domains for SPA session auth
resources/js/
  Pages/
    Welcome.vue          # generic starter landing page
    Dashboard.vue        # account overview cards (auth+verified)
    Profile.vue          # profile info, password, 2FA management (auth+verified)
    Auth/                # Login, Register, VerifyEmail, ForgotPassword, ResetPassword,
                         # TwoFactorChallenge, ConfirmPassword
  Layouts/
    AppLayout.vue        # top nav with user dropdown, locale switcher, dark mode, toast
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
    useFlashToast.ts     # surfaces flash messages as PrimeVue toasts
  i18n/
    en.ts
    no.ts
resources/css/
  app.css                # Tailwind v4 @theme block — dark-blue palette --color-dark-*
database/
  seeders/
    DatabaseSeeder.php           # seeded admin (from .env) plus demo editors and users
    RoleAndPermissionSeeder.php  # Admin / Editor / User roles, starter permissions
docker/
  entrypoint.sh
  nginx.conf
  supervisord.conf       # runs php-fpm, nginx, vite, reverb
scripts/
  init-starter.sh       # interactive project bootstrap for .env
tests/
  Feature/
    Auth/                # Login, Registration, EmailVerification, PasswordReset,
                         # PasswordConfirmation, TwoFactorAuthentication, RolesAndPermissions
    DashboardTest.php
    ProfileTest.php
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

## Authentication

Auth is powered by **Laravel Fortify** (headless) and **Laravel Sanctum** (SPA session + API tokens). All views are rendered as Inertia pages from `app/Providers/FortifyServiceProvider.php`. Custom behavior lives in:

- `app/Actions/Fortify/` — `CreateNewUser` (assigns `User` role on register), `UpdateUserProfileInformation` (uses `first_name`/`last_name`, default error bag so Inertia surfaces errors), `UpdateUserPassword` (default error bag), `ResetUserPassword`
- `app/Http/Responses/LoginResponse.php` — sends verified users to `/dashboard`, unverified users to `/email/verify`
- `app/Http/Responses/RegisterResponse.php` — sends new users to `/email/verify`

`config('fortify.home')` is `/dashboard`. After login/register, that's where Fortify redirects authenticated users — including the "redirect away from login/register/forgot-password" guard.

Included flows: login, registration, email verification, password reset, two-factor authentication (TOTP + recovery codes), password confirmation. The `Dashboard` and `Profile` pages are gated behind `['auth', 'verified']` in `routes/web.php`.

When customizing the Fortify actions, **do not use `validateWithBag()`** unless you also wire the error bag through Inertia's `useForm({ ... }, { errorBag: ... })`. The starter actions use `validate()` so errors flow through the default bag and the existing Profile.vue forms work without extra config.

## Roles & Permissions

Seeded roles:

- `Admin` — all permissions
- `Editor` — `view dashboard`, `manage content`, `manage settings`, `manage profile`
- `User` — `view dashboard`, `manage profile` (assigned to all new registrations by `CreateNewUser`)

Starter permissions:

- `view dashboard`
- `manage users`
- `manage roles`
- `manage content`
- `manage settings`
- `manage profile`

Roles and permissions are shared on every Inertia request as `auth.user.roles` and `auth.user.permissions` (see `HandleInertiaRequests`). These are starter defaults — adapt them early in a real project.

## UI Theme & Toasts

The starter is **dark-first**: `UserSetting::$defaults['dark_mode']` is `true`, and `resources/views/app.blade.php` runs an inline pre-paint script that adds the `dark` class to `<html>` before Vue mounts to avoid a flash of light theme.

The dark palette lives in `resources/css/app.css` under the Tailwind v4 `@theme` block as `--color-dark-950` … `--color-dark-300` (dark blues). Use `dark:bg-dark-900`, `dark:border-dark-700`, etc. instead of Tailwind's default slate.

Flash messages from the backend are surfaced as PrimeVue toasts via `resources/js/composables/useFlashToast.ts`, which is invoked once in `AppLayout.vue`. PrimeVue's `ToastService` is registered in `resources/js/app.ts`. To raise a toast from any page, use `useToast()` from `primevue/usetoast`; to raise one from the backend, set `flash.success` / `flash.error` / `flash.status` in the redirect.

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
