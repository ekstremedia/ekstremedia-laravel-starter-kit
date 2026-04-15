<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemInfoController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\AvatarController;
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

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

// Authenticated + verified routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/profile', fn () => Inertia::render('Profile'))->name('profile');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->name('profile.avatar.store');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');
});

// Admin routes
Route::middleware(['auth', 'verified', 'role:Admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Overview'))->name('overview');

        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('roles', RoleController::class)->except(['show']);

        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

        Route::post('health/queue', [HealthController::class, 'dispatchPing'])->name('health.queue');
        Route::post('health/broadcast', [HealthController::class, 'broadcastPing'])->name('health.broadcast');
        Route::get('health/queue-last', [HealthController::class, 'queueLast'])->name('health.queue.last');

        Route::get('mail', [MailSettingsController::class, 'show'])->name('mail.show');
        Route::patch('mail', [MailSettingsController::class, 'update'])->name('mail.update');
        Route::post('mail/test', [MailSettingsController::class, 'test'])->name('mail.test');

        Route::get('system', [SystemInfoController::class, 'show'])->name('system.show');
        Route::get('health', fn () => redirect()->route('admin.system.show'))->name('health.show');
    });
