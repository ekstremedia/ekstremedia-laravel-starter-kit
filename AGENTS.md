# Laravel Starter Kit

An opinionated starter for new Laravel products. Generic foundation — reshape for your domain.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.4, PostgreSQL 17, Redis 7
- **Auth:** Fortify (headless) + Sanctum (SPA session + API tokens)
- **Frontend:** Vue 3 + TypeScript, Inertia.js v3, Tailwind CSS v4, PrimeVue v4
- **Testing:** Pest, Larastan (level 5), Vitest (frontend)
- **Queues:** Redis + Horizon (Supervisor)
- **Monitoring:** Pulse (`/pulse`), Horizon (`/horizon`), Sentry (optional)
- **Other:** Docker, vue-i18n, Spatie Permission, Spatie Activitylog, Laravel Reverb, MJML email templates

## Commands

All commands run inside the `app` container:

```sh
docker compose exec app php artisan <cmd>
docker compose exec app composer <cmd>
docker compose exec app npm <cmd>
docker compose exec app vendor/bin/pint --dirty --format agent
docker compose exec app php artisan test --compact
```

## Coding Rules

### PHP
- Curly braces for all control structures, even single-line
- PHP 8 constructor property promotion
- Explicit return types and parameter type hints
- PHPDoc with array shape types
- Run `vendor/bin/pint --dirty --format agent` after modifying PHP files
- Use `php artisan make:*` with `--no-interaction` to scaffold files

### Vue / Frontend
- Every visible string must use `t('key')` from vue-i18n (see Localization section)
- Vue components must have a single root element (Inertia requirement)
- PrimeVue label props use dynamic binding: `:label="t('...')"` not `label="..."`
- Dark palette: use `dark:bg-dark-900`, `dark:border-dark-700` etc. (custom blues in `app.css @theme`)
- Check sibling files for conventions before creating new components

### Testing
- Every change must be tested. Write or update a Pest test, then run it
- Use factories for models, `fake()` for test data
- `php artisan make:test --pest {name}` for feature tests, `--unit` for unit tests
- Do NOT delete tests without approval

## Architecture

Inertia SPA: Laravel handles routing/auth/controllers, Vue renders UI from `resources/js/Pages`.

### Admin Section (`/admin/*`, gated by `role:Admin`)

`/admin` overview, `/admin/users` CRUD, `/admin/roles`, `/admin/permissions`, `/admin/activity`, `/admin/mail` (SMTP + MJML template editor), `/admin/settings`, `/admin/backups`, `/admin/system`, `/horizon`, `/pulse`, `/log-viewer`

### Authentication (Fortify + Sanctum)

Views rendered as Inertia pages via `FortifyServiceProvider`. Custom behavior in `app/Actions/Fortify/`, `app/Http/Responses/Login|RegisterResponse.php`.

`config('fortify.home')` is `/app` (the post-login landing). Flows: login, registration, email verification, password reset, 2FA (TOTP + recovery codes), password confirmation.

**Do not use `validateWithBag()`** in Fortify actions unless you also wire the error bag through Inertia's `useForm()`.

### Roles & Permissions (Spatie)

Seeded: `Admin` (all), `Editor` (dashboard, resources, settings, profile), `User` (dashboard, settings, profile — assigned on registration). Shared on every Inertia request as `auth.user.roles` / `auth.user.permissions`.

## Localization

Supports English (`en`) and Norwegian (`no`). **Every user-facing string must be translated.**

1. Use `t('key')` from vue-i18n in every `.vue` file. Import `useI18n` + `const { t } = useI18n()`.
2. Update **both** `resources/js/i18n/en.ts` and `no.ts` in the same commit.
3. Group keys by domain: `admin.users.*`, `admin.mail.*`, `notifications.*`, `common.*`. Reuse `common.*` for shared words.
4. Dynamic strings: `t('key', { name: value })`.
5. For reactive arrays needing `t()`, wrap in `computed()`.

## Notifications

DB-backed notification system with MJML email templates. Bell icon in `AppLayout.vue` shows unread count; dropdown fetches from `GET /notifications`.

### When to notify
- Account lifecycle: welcome, verification, password reset, banned
- Customer membership: added/removed
- Admin actions: role changes, 2FA reset
- Workflow events: assignments, mentions, invites

### Adding a notification
1. `php artisan make:notification SomethingHappened`
2. Use `UsesEmailTemplate` trait for MJML emails
3. `via()` → `['database', 'mail']`
4. `toArray()` → `['title' => '...', 'message' => '...', 'icon' => 'pi-...']`
5. Add template rows in `EmailTemplateSeeder` for both `en` and `no` locales
6. Optional admin actions: add "Notify user" checkbox (see `Admin/Users/Edit.vue`)

## Email Templates (MJML)

Admin-editable email content via `/admin/mail` → Email Templates tab. Templates stored in `email_templates` table with per-locale variants. MJML layout in `resources/views/mjml/layout.blade.php` wraps the editable content (subject, heading, body, CTA button). Compiled HTML cached in `compiled_html` column.

All notifications use the `UsesEmailTemplate` trait which resolves the user's locale and loads the matching template. Custom `VerifyEmailNotification` and `ResetPasswordNotification` replace Laravel defaults.

## Multi-Customer Tenancy (optional, off by default)

`stancl/tenancy` v3, disabled by default. Flip `TENANCY_ENABLED=true` + `migrate:fresh --seed` to activate.

**Vocabulary:** "Customer" = user-facing (URLs, UI, controllers). "Tenant" = package-facing (model, DB tables, config).

| Customer-facing | Tenant-facing |
|----------------|---------------|
| `/c/{customer}/...` URLs | `App\Models\Tenant`, `tenants` table |
| `/admin/customers` | `config/tenancy.php` |
| `CustomerController`, `useCustomer()` | `InitializeTenancyByPath` |

**When enabled:** path-based `/c/{slug}/...`, PG schema per customer, shared users in `public` schema, `tenant_user` pivot for membership, Admins bypass membership checks.

**When disabled:** routes at root, `tenants` table stays empty, no migration needed to enable later.

**Testing:** `.env.testing` forces `TENANCY_ENABLED=true`. `tests/Pest.php` strips bootstrappers for SQLite. Helpers: `createCustomer()`, `joinCustomer()`, `customerUrl()`.

## UI Theme

Dark-first (`dark_mode` defaults `true`). Custom blue palette `--color-dark-950` through `--color-dark-300` in `app.css @theme`. PrimeVue Aura preset with dark mode overrides for DataTable, Tabs.

Flash → PrimeVue toast via `useFlashToast.ts`. Backend: `->with('success', '...')`.

## Settings System

User preferences in `user_settings.settings` (JSONB): `locale`, `dark_mode`. Flow: localStorage → Inertia props override → debounced `PATCH /settings`.

## Observability

- **Sentry** — `SENTRY_LARAVEL_DSN` in `.env`
- **Backups** — `spatie/laravel-backup`, schedule in `routes/console.php`, UI at `/admin/backups`
- **Log Viewer** — `/log-viewer` (Admin gate)
- **Impersonation** — `lab404/laravel-impersonate`, amber banner, activity logged
- **Static analysis** — Larastan level 5: `make stan`
- **Pre-commit hooks** — Husky + lint-staged (pint + vue-tsc)

## Docker & Dev

`make init` → bootstrap `.env`. `make build` → start containers. `make rebuild` → full reset with `migrate:fresh --seed`.

Dev login: `DEV_EASY_LOGIN_ENABLED=true` shows shortcut on login page (local/test only).

Websockets: Reverb runs in Supervisor. `VITE_REVERB_HOST` = public hostname for browser WS connection.

## Laravel Boost MCP

`.mcp.json.example` → copy to `.mcp.json`, replace `<PROJECT_ROOT>` with your clone path. Runs inside the `app` container via `docker compose exec -T app php artisan boost:mcp`.

## Maintenance Rules

- Keep behavior generic — no domain-specific nouns or seed data
- Environment-driven configuration over hardcoded values
- New UI strings → update both `en.ts` and `no.ts`
- New settings → update `UserSetting::$defaults` + TypeScript interface
- Verify both host and container test runs
