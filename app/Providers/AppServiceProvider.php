<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // personal_access_tokens lives on the central schema — point Sanctum
        // at our pinned subclass before any token query runs, or tenant-scoped
        // requests will try to read the table from the active tenant schema.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Catch N+1 queries at their source. We intentionally leave
        // preventSilentlyDiscardingAttributes + preventAccessingMissingAttributes
        // disabled — they surface too many legitimate patterns (partial
        // selects, dynamic attributes) that would derail existing code.
        // Production stays permissive regardless, so a stray unknown attribute
        // never takes the site down in the field.
        Model::preventLazyLoading(! $this->app->isProduction());

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

        // Laravel's default /up route dispatches DiagnosingHealth before it
        // returns 200 — failing a listener flips the response to 500. Hook
        // in a DB ping (and Redis when Redis is the cache/queue driver) so
        // /up is a real dependency probe, not just "PHP booted".
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::connection()->getPdo();

            if (in_array('redis', [(string) config('cache.default'), (string) config('queue.default'), (string) config('session.driver')], true)) {
                Redis::connection()->ping();
            }
        });
    }
}
