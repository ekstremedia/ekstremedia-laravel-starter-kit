<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use App\Models\Tenant;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'first_name' => $request->user()->first_name,
                    'last_name' => $request->user()->last_name,
                    'email' => $request->user()->email,
                    'email_verified_at' => $request->user()->email_verified_at,
                    'created_at' => $request->user()->created_at,
                    'two_factor_enabled' => ! is_null($request->user()->two_factor_confirmed_at),
                    'full_name' => $request->user()->fullName(),
                    'avatar_url' => $request->user()->avatarUrl('avatar'),
                    'avatar_thumb_url' => $request->user()->avatarUrl('thumb'),
                    'roles' => $request->user()->getRoleNames()->toArray(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name')->toArray(),
                    'unread_notifications_count' => $request->user()->unreadNotifications()->count(),
                    'is_impersonating' => session()->has('impersonated_by'),
                ] : null,
            ],
            'locale' => app()->getLocale(),
            'debug' => [
                'easy_login_enabled' => (app()->isLocal() || app()->runningUnitTests()) && config('dev.easy_login_enabled'),
            ],
            // Resolved per-user preferences for authenticated users, defaults for
            // guests. Named `user_settings` so it cannot collide with a page-level
            // `settings` prop on admin pages (app settings, mail settings, …).
            'user_settings' => $request->user()
                ? $request->user()->settings()->resolved()
                : UserSetting::$defaults,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
            'app_settings' => fn () => $this->appSettings(),
            'tenancy' => [
                'enabled' => (bool) config('tenancy.enabled'),
            ],
            'customer' => fn () => $this->currentCustomer($request),
            'customers' => fn () => $this->availableCustomers($request),
        ];
    }

    /**
     * The customer the request is currently scoped to, or null on central routes.
     *
     * @return array<string, mixed>|null
     */
    private function currentCustomer(Request $request): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        /** @var Tenant $customer */
        $customer = tenancy()->tenant;

        return [
            'id' => $customer->id,
            'slug' => $customer->slug,
            'name' => $customer->name,
        ];
    }

    /**
     * Customers the authenticated user can enter. Admins see every active one;
     * non-admins see only the customers they are a member of.
     *
     * @return array<int, array<string, mixed>>
     */
    private function availableCustomers(Request $request): array
    {
        if (! config('tenancy.enabled')) {
            return [];
        }

        $user = $request->user();

        if (! $user) {
            return [];
        }

        $query = $user->hasRole('Admin')
            ? Tenant::query()->where('status', 'active')
            : $user->customers()->where('status', 'active');

        /** @var Collection<int, Tenant> $customers */
        $customers = $query->orderBy('name')->get();

        return $customers
            ->map(fn (Tenant $customer) => [
                'id' => $customer->id,
                'slug' => $customer->slug,
                'name' => $customer->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function appSettings(): array
    {
        try {
            $s = AppSetting::current();

            return [
                'registration_open' => $s->registration_open,
                'login_enabled' => $s->login_enabled,
                'announcement' => $s->announcement_banner
                    ? ['text' => $s->announcement_banner, 'severity' => $s->announcement_severity]
                    : null,
            ];
        } catch (Throwable) {
            return [
                'registration_open' => true,
                'login_enabled' => true,
                'announcement' => null,
            ];
        }
    }
}
