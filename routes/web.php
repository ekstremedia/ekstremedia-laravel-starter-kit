<?php

use App\Http\Controllers\Auth\DevLoginController;
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
});
