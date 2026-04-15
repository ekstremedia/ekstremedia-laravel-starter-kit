<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

$adminRoutes = [
    ['GET', '/admin'],
    ['GET', '/admin/users'],
    ['GET', '/admin/users/create'],
    ['GET', '/admin/roles'],
    ['GET', '/admin/permissions'],
    ['GET', '/admin/activity'],
    ['GET', '/admin/mail'],
    ['GET', '/admin/system'],
];

it('redirects guests from admin routes to login', function (string $method, string $uri) {
    $this->call($method, $uri)->assertRedirect('/login');
})->with($adminRoutes);

it('forbids non-admins from admin routes', function (string $method, string $uri) {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)->call($method, $uri)->assertForbidden();
})->with($adminRoutes);

it('allows admins to access admin routes', function (string $method, string $uri) {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)->call($method, $uri)->assertOk();
})->with($adminRoutes);
