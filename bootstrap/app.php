<?php

use App\Http\Middleware\EnforceAppSettings;
use App\Http\Middleware\EnsureChatEnabled;
use App\Http\Middleware\EnsureStorageAvailable;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancyByPath;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocaleFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        // Runs before route matching so 404s / redirects also get the headers
        // and correlation id.
        $middleware->prepend([
            RequestId::class,
            SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            SetLocaleFromUser::class,
            HandleInertiaRequests::class,
            EnsureUserIsNotBanned::class,
            EnforceAppSettings::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'chat.enabled' => EnsureChatEnabled::class,
            'storage.available' => EnsureStorageAvailable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Render a token-styled Inertia error page for the common HTTP
        // error codes outside local/testing (so stack traces still surface
        // during development). 419 falls back to the standard "page expired"
        // redirect so form resubmits keep their old behavior.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();
            $isLocalOrTesting = app()->environment(['local', 'testing']);

            if ($status === 419) {
                return back()->with('flash', [
                    'level' => 'warning',
                    'message' => 'The page expired, please try again.',
                ]);
            }

            // 403 / 404 always get the friendly Inertia page — there's no
            // stack trace to inspect, and a plain Symfony error page is a
            // worse developer experience than a styled in-app 404. For 500
            // / 503 in local/testing we let Ignition render the stack trace.
            $inertiaSafeStatuses = $isLocalOrTesting ? [403, 404] : [403, 404, 500, 503];

            if (in_array($status, $inertiaSafeStatuses, true)) {
                return Inertia::render('Errors/Error', [
                    'status' => $status,
                    'message' => $exception instanceof HttpExceptionInterface
                        ? $exception->getMessage()
                        : '',
                ])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
