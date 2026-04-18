<?php

declare(strict_types=1);

use App\Http\Controllers\AvatarController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Customer-scoped routes (shared between single- and multi-tenant modes)
|--------------------------------------------------------------------------
|
| This file lists the routes that a logged-in user owns inside a customer.
| It's mounted from `bootstrap/app.php` in one of two shapes depending on
| `config('tenancy.enabled')`:
|
|   - enabled  → prefix `/c/{customer}`, middleware auth+verified+InitializeTenancyByPath,
|                name prefix `customer.`
|   - disabled → root paths, middleware auth+verified, no name prefix
|
| Keep this file as a flat list of route definitions only — no `prefix()`,
| `middleware()`, or `name()` wrappers here. The mounting side decides that.
*/

Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

Route::get('/profile', fn () => Inertia::render('Profile'))->name('profile');
Route::post('/profile/avatar', [AvatarController::class, 'store'])->name('profile.avatar.store');
Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
