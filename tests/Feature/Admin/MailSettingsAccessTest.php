<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('renders the mail settings page for admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/admin/mail')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Mail')
            ->has('settings.mailer')
            ->has('settings.from_address')
            ->where('settings.password', null)
        );
});

it('masks the password in the response after save', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)->patch('/admin/mail', [
        'mailer' => 'smtp',
        'host' => 'h',
        'port' => 25,
        'encryption' => null,
        'username' => 'u',
        'password' => 'secret-xyz',
        'from_address' => 'a@b.test',
        'from_name' => 'T',
        'enabled' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin/mail')
        ->assertInertia(fn ($page) => $page
            ->where('settings.password', '••••••')
        );
});

it('rejects invalid mail settings payloads', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->patch('/admin/mail', [
            'mailer' => '',
            'port' => 999999,
            'from_address' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['mailer', 'port', 'from_address']);
});
