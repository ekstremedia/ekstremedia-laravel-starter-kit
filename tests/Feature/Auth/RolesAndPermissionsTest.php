<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('creates the admin role', function () {
    expect(Role::where('name', 'Admin')->exists())->toBeTrue();
});

it('creates the user role', function () {
    expect(Role::where('name', 'User')->exists())->toBeTrue();
});

it('creates expected permissions', function () {
    $expected = [
        'view dashboard',
        'manage users',
        'manage roles',
        'manage settings',
        'manage profile',
    ];

    foreach ($expected as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue();
    }
});

it('assigns all permissions to admin role', function () {
    $adminRole = Role::findByName('Admin');
    $allPermissions = Permission::all();

    expect($adminRole->permissions->count())->toBe($allPermissions->count());
});

it('can assign admin role to a user', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    expect($user->hasRole('Admin'))->toBeTrue();
    expect($user->can('manage users'))->toBeTrue();
});
