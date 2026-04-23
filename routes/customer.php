<?php

declare(strict_types=1);

use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileItemController;
use App\Http\Controllers\FileShareController;
use App\Http\Controllers\FileTrashController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/profile', fn () => Inertia::render('Profile'))->name('profile');
Route::post('/profile/avatar', [AvatarController::class, 'store'])->name('profile.avatar.store');
Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
Route::delete('/notifications', [NotificationController::class, 'destroyAll'])->name('notifications.destroyAll');

// Personal file system (tenant + per-user opt-in enforced in controller)
Route::get('/files', [FileItemController::class, 'index'])->name('files.index');
Route::get('/files/trash', [FileTrashController::class, 'index'])->name('files.trash.index');
Route::post('/files/trash/{id}/restore', [FileTrashController::class, 'restore'])
    ->whereNumber('id')
    ->name('files.trash.restore');
Route::delete('/files/trash/{id}', [FileTrashController::class, 'forceDelete'])
    ->whereNumber('id')
    ->name('files.trash.forceDelete');
Route::delete('/files/trash', [FileTrashController::class, 'empty'])->name('files.trash.empty');
Route::get('/files/{folder}', [FileItemController::class, 'index'])
    ->whereNumber('folder')
    ->name('files.show');
Route::post('/files/folder', [FileItemController::class, 'storeFolder'])->name('files.folder.store');
Route::post('/files', [FileItemController::class, 'store'])
    ->middleware('storage.available')
    ->name('files.store');
Route::patch('/files/{file}', [FileItemController::class, 'update'])
    ->whereNumber('file')
    ->name('files.update');
Route::delete('/files/{file}', [FileItemController::class, 'destroy'])
    ->whereNumber('file')
    ->name('files.destroy');
Route::get('/files/{file}/download', [FileItemController::class, 'download'])
    ->whereNumber('file')
    ->name('files.download');

// Share-link management (creating / listing / revoking links). Public viewing
// of shared items is in routes/web.php under /share/*.
Route::get('/files/{file}/shares', [FileShareController::class, 'index'])
    ->whereNumber('file')
    ->name('files.shares.index');
Route::post('/files/{file}/shares', [FileShareController::class, 'store'])
    ->whereNumber('file')
    ->name('files.shares.store');
Route::post('/files/{file}/shares/signed', [FileShareController::class, 'quickSignedLink'])
    ->whereNumber('file')
    ->name('files.shares.signed');
Route::delete('/files/shares/{share}', [FileShareController::class, 'destroy'])->name('files.shares.destroy');
