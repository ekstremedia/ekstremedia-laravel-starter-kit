# Laravel Starter Kit

An opinionated starter repository for new Laravel products. The goal is to avoid redoing the same baseline setup on every project while still keeping the code generic and easy to reshape for a real domain.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.4, PostgreSQL 17, Redis 7
- **Auth:** Laravel Fortify (headless backend) + Laravel Sanctum (SPA session + API tokens)
- **Frontend:** Vue 3 + TypeScript, Inertia.js (SPA mode), Tailwind CSS v4, PrimeVue v4
- **Testing:** Pest
- **Queues:** Redis + Laravel Horizon (runs in Supervisor)
- **Monitoring:** Laravel Pulse (`/pulse`), Laravel Horizon (`/horizon`)
- **Activity log:** Spatie Laravel Activitylog (custom viewer under `/admin/activity`)
- **Other:** Docker, Mailpit, GSAP, vue-i18n, Spatie Laravel Permission, Laravel Reverb

## Running Commands (Container-Only)

All commands run inside the `app` container — never on the host. The repo mounts the working directory, so edits on host are visible.

```sh
docker compose exec app composer <cmd>
docker compose exec app php artisan <cmd>
docker compose exec app npm <cmd>
docker compose exec app vendor/bin/pint --dirty --format agent
docker compose exec app php artisan test --compact
```

`.mcp.json` already wires Laravel Boost through `docker compose exec app` — so the MCP server runs inside the container automatically.

## Admin Section

Gated by `role:Admin` (spatie). All pages live under `/admin/*`:

- `/admin` — overview
- `/admin/users` — user CRUD + role assignment
- `/admin/roles` — role CRUD + permission sync
- `/admin/permissions` — permissions registry
- `/admin/activity` — activity log viewer (filter by user, date, log name, event)
- `/admin/health` — queue + Reverb + Redis health with test ping buttons
- `/admin/mail` — SMTP settings + test send (singleton `mail_settings` row; password encrypted)
- `/admin/system` — PHP / Laravel / drivers / cache / extensions snapshot
- `/horizon` — queue dashboard (admin-only gate in `HorizonServiceProvider::gate()`)
- `/pulse` — app metrics (admin-only gate in `AppServiceProvider::boot()` via `viewPulse`)

Supervisor programs (`docker/supervisord.conf`): php-fpm, nginx, reverb, **horizon**, **pulse-check**, **pulse-work**, **scheduler**, vite.

## Observability, Backups & Productivity Stack

- **Sentry** (`sentry/sentry-laravel`) — set `SENTRY_LARAVEL_DSN` in `.env` to enable. Exceptions route through `\Sentry\Laravel\Integration::handles()` in `bootstrap/app.php`. Leave DSN blank for local dev.
- **Backups** (`spatie/laravel-backup`) — `config/backup.php` reads `DB_CONNECTION`. Schedule (from `routes/console.php`): `backup:clean` at 01:30, `backup:run` at 02:00, `backup:monitor` at 06:00. Admin UI at `/admin/backups`. Requires `pg_dump` (installed in the Dockerfile via `postgresql-client`). The `scheduler` supervisor program (runs `schedule:work`) drives the cron.
- **Log Viewer** (`opcodesio/log-viewer`) — mounted at `/log-viewer`, gated by the `viewLogViewer` Gate in `AppServiceProvider` (Admin only). Shows raw `storage/logs/*` files — distinct from the activity log.
- **Impersonation** (`lab404/laravel-impersonate`) — Admins see a yellow "login as" button next to each non-admin in `/admin/users`. Taking over redirects to `/dashboard` with an amber banner; pressing "Stop impersonating" returns to the original admin. Both actions write to the `impersonation` activity log.
- **Notifications inbox** — DB-backed. `$user->unreadNotifications()->count()` is shared via Inertia as `auth.user.unread_notifications_count` and rendered as a red badge on the bell icon in `AppLayout.vue`. Endpoints: `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all`, `DELETE /notifications/{id}`.
- **Static analysis** — Larastan (`phpstan.neon`, level 5). Run with `make stan` or `vendor/bin/phpstan analyse`. Part of the backend CI job.
- **Pre-commit hooks** — Husky + lint-staged. PHP → `pint --dirty`, TS/Vue → `vue-tsc --noEmit`. Configured in `package.json` (`lint-staged` key) and `.husky/pre-commit`. Install hooks with `npm install` (Husky's `prepare` script runs automatically).

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
- `Editor` — `view dashboard`, `manage resources`, `manage settings`, `manage profile`
- `User` — `view dashboard`, `manage settings`, `manage profile` (assigned to all new registrations by `CreateNewUser`)

Starter permissions:

- `view dashboard`
- `manage users`
- `manage roles`
- `manage resources`
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

## Laravel Boost MCP

The repo ships a `.mcp.json` that launches Laravel Boost **inside the `app` container** so PHP/Composer/Artisan all run against the containerized stack:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "docker",
            "args": ["compose", "-f", "/www/laravel-starter-kit/docker-compose.yml",
                     "exec", "-T", "app", "php", "artisan", "boost:mcp"]
        }
    }
}
```

Notes:

- `-T` disables TTY allocation so MCP's JSON-RPC stdio framing is not corrupted.
- `docker compose exec` requires the `app` service to already be running (`docker compose up -d` or `make build`). It will not auto-start the stack.
- Sanity check the wiring with `docker compose exec -T app php artisan boost:mcp <<< ''` — a JSON-RPC parse error reply means the server is alive.
- If your MCP client cannot find `docker` on PATH, replace `"command": "docker"` with the absolute path from `which docker`.

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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v3
- laravel-echo (ECHO) - v2
- vue (VUE) - v3
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fortify-development` — ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-vue-development` — Develops Inertia.js v3 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using <Link>, <Form>, useForm, useHttp, setLayoutProps, or router; working with deferred props, prefetching, optimistic updates, instant visits, or polling; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation.
- `echo-development` — Develops real-time broadcasting with Laravel Echo. Activates when setting up broadcasting (Reverb, Pusher, Ably); creating ShouldBroadcast events; defining broadcast channels (public, private, presence, encrypted); authorizing channels; configuring Echo; listening for events; implementing client events (whisper); setting up model broadcasting; broadcasting notifications; or when the user mentions broadcasting, Echo, WebSockets, real-time events, Reverb, or presence channels.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.
- `laravel-permission-development` — Build and work with Spatie Laravel Permission features, including roles, permissions, middleware, policies, teams, and Blade directives.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
