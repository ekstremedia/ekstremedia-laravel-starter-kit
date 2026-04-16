<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('rejects creating a user with an existing email', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($this->admin)
        ->post('/admin/users', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'taken@example.test',
            'password' => 'Password#1',
            'password_confirmation' => 'Password#1',
        ])
        ->assertSessionHasErrors('email');
});

it('rejects unknown roles when assigning', function () {
    $this->actingAs($this->admin)
        ->post('/admin/users', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'new@example.test',
            'password' => 'Password#1',
            'password_confirmation' => 'Password#1',
            'roles' => ['NonExistentRole'],
        ])
        ->assertSessionHasErrors('roles.0');
});

it('allows updating a user without changing password when blank', function () {
    $user = User::factory()->create(['password' => bcrypt('old-pass')]);
    $user->assignRole('User');
    $hashBefore = $user->password;

    $this->actingAs($this->admin)
        ->put("/admin/users/{$user->id}", [
            'first_name' => 'Same',
            'last_name' => 'Same',
            'email' => $user->email,
            'roles' => ['User'],
        ])
        ->assertRedirect('/admin/users');

    expect($user->fresh()->password)->toBe($hashBefore);
});

it('allows the same email when editing the same user', function () {
    $user = User::factory()->create(['email' => 'stays@example.test']);
    $user->assignRole('User');

    $this->actingAs($this->admin)
        ->put("/admin/users/{$user->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'stays@example.test',
            'roles' => ['User'],
        ])
        ->assertRedirect('/admin/users');
});

it('forbids non-admins from creating users', function () {
    $user = User::factory()->create();
    $user->assignRole('Editor');

    $this->actingAs($user)
        ->post('/admin/users', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'new@example.test',
            'password' => 'Password#1',
            'password_confirmation' => 'Password#1',
        ])
        ->assertForbidden();
});
