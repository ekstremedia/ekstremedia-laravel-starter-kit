<?php

use App\Http\Middleware\EnforceAppSettings;
use App\Http\Middleware\EnsureChatEnabled;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancyByPath;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Same routes file, two shapes. See `config/tenancy.php` and
            // `routes/customer.php` for the contract.
            $customerRoutes = __DIR__.'/../routes/customer.php';

            if (config('tenancy.enabled')) {
                Route::prefix('c/{customer}')
                    ->middleware(['web', 'auth', 'verified', InitializeTenancyByPath::class])
                    ->name('customer.')
                    ->group($customerRoutes);
            } else {
                Route::middleware(['web', 'auth', 'verified'])
                    ->group($customerRoutes);
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsureUserIsNotBanned::class,
            EnforceAppSettings::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'chat.enabled' => EnsureChatEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
