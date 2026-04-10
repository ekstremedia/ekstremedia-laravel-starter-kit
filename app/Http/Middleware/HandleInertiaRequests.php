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
                'user' => $request->user() ? array_merge(
                    $request->user()->toArray(),
                    [
                        'full_name' => $request->user()->fullName(),
                        'roles' => $request->user()->getRoleNames()->toArray(),
                        'permissions' => $request->user()->getAllPermissions()->pluck('name')->toArray(),
                    ],
                ) : null,
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
