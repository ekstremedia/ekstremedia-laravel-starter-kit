<?php

namespace App\Http\Middleware;

use App\Models\UserSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
            // Resolved settings for authenticated users, defaults for guests
            'settings' => $request->user()
                ? $request->user()->settings()->resolved()
                : UserSetting::$defaults,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
