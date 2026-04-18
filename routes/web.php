<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AppSettingsController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\ImpersonateController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemInfoController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\CustomerLandingController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Dev easy-login (local/test only)
if (app()->isLocal() || app()->runningUnitTests()) {
    Route::middleware('guest')->group(function () {
        Route::post('/login/dev', [DevLoginController::class, 'store'])->name('login.dev');
    });
}

// Authenticated routes (user-level, customer-agnostic)
Route::middleware('auth')->group(function () {
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

// Post-login landing — redirects to the user's customer or renders the picker.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/app', CustomerLandingController::class)->name('app.landing');
});

// Admin routes (system super-user — spans all tenants)
Route::middleware(['auth', 'verified', 'role:Admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Overview'))->name('overview');

        Route::resource('users', UserController::class);
        Route::post('users/{user}/verify', [UserController::class, 'verify'])->name('users.verify');
        Route::post('users/{user}/unverify', [UserController::class, 'unverify'])->name('users.unverify');
        Route::post('users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
        Route::post('users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');
        Route::post('users/{user}/resend-verification', [UserController::class, 'resendVerification'])->name('users.resendVerification');
        Route::post('users/{user}/reset-2fa', [UserController::class, 'reset2fa'])->name('users.reset2fa');
        Route::post('users/{user}/send-password-reset', [UserController::class, 'sendPasswordReset'])->name('users.sendPasswordReset');
        Route::post('users/{user}/notify-test', [UserController::class, 'notifyTest'])->name('users.notifyTest');

        Route::resource('roles', RoleController::class)->except(['show']);

        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

        Route::post('health/queue', [HealthController::class, 'dispatchPing'])->name('health.queue');
        Route::post('health/broadcast', [HealthController::class, 'broadcastPing'])->name('health.broadcast');
        Route::get('health/queue-last', [HealthController::class, 'queueLast'])->name('health.queue.last');

        Route::patch('mail/templates/{template}', [EmailTemplateController::class, 'update'])->name('mail.templates.update');
        Route::get('mail/templates/{template}/preview', [EmailTemplateController::class, 'preview'])->name('mail.templates.preview');
        Route::post('mail/templates/{template}/test', [EmailTemplateController::class, 'testSend'])->name('mail.templates.test');

        Route::get('mail', [MailSettingsController::class, 'show'])->name('mail.show');
        Route::patch('mail', [MailSettingsController::class, 'update'])->name('mail.update');
        Route::post('mail/test', [MailSettingsController::class, 'test'])->name('mail.test');

        Route::get('system', [SystemInfoController::class, 'show'])->name('system.show');
        Route::get('health', fn () => redirect()->route('admin.system.show'))->name('health.show');

        Route::get('settings', [AppSettingsController::class, 'show'])->name('settings.show');
        Route::patch('settings', [AppSettingsController::class, 'update'])->name('settings.update');

        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups/run', [BackupController::class, 'run'])->name('backups.run');
        Route::post('backups/clean', [BackupController::class, 'clean'])->name('backups.clean');

        // Landlord — customer management. Only registered when multi-tenancy is
        // active so single-tenant installs don't expose a CRUD for a concept
        // that doesn't apply.
        if (config('tenancy.enabled')) {
            Route::resource('customers', CustomerController::class)->except(['show']);
            Route::post('customers/{customer}/members', [CustomerController::class, 'attachMember'])->name('customers.members.attach');
            Route::delete('customers/{customer}/members/{user}', [CustomerController::class, 'detachMember'])->name('customers.members.detach');

            Route::post('users/{user}/customers', [UserController::class, 'attachCustomer'])->name('users.customers.attach');
            Route::delete('users/{user}/customers/{customer}', [UserController::class, 'detachCustomer'])->name('users.customers.detach');
        }

        Route::post('users/{user}/impersonate', [ImpersonateController::class, 'take'])->name('users.impersonate');
    });

// Impersonation — leave action must be available from within the impersonated session
Route::middleware('auth')->group(function () {
    Route::post('/impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
});
