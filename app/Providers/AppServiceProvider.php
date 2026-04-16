<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Authenticated users visiting guest-only pages (/login, /register, ...)
        // land on the tenant landing page, which dispatches them into their
        // workspace (or renders the picker for admins / multi-tenant users).
        RedirectIfAuthenticated::redirectUsing(fn () => route('app.landing'));

        Gate::define('viewPulse', function ($user = null) {
            return $user !== null && $user->hasRole('Admin');
        });

        Gate::define('viewLogViewer', function ($user = null) {
            return $user !== null && $user->hasRole('Admin');
        });
    }
}
