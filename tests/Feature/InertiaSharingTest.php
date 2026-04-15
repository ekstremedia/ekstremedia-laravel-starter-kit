<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('public');
});

it('shares avatar urls as null when no photo uploaded', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.avatar_url', null)
            ->where('auth.user.avatar_thumb_url', null)
        );
});

it('shares avatar urls after upload', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $user->addMedia(UploadedFile::fake()->image('a.png', 400, 400))
        ->toMediaCollection('avatar');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.avatar_url', fn ($v) => is_string($v) && str_contains($v, 'avatar'))
            ->where('auth.user.avatar_thumb_url', fn ($v) => is_string($v))
        );
});

it('shares roles and permissions for authenticated users', function () {
    $user = User::factory()->create();
    $user->assignRole('Editor');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.roles', ['Editor'])
            ->has('auth.user.permissions')
        );
});

it('shares null auth.user for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.user', null));
});

it('shares user settings for authenticated users', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('user_settings.locale')
            ->has('user_settings.dark_mode')
        );
});
