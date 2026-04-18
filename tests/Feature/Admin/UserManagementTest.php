<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('creates a user with roles', function () {
    $this->actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.test',
        'password' => 'Password#1',
        'password_confirmation' => 'Password#1',
        'roles' => ['Editor'],
    ])->assertRedirect('/admin/users');

    $user = User::where('email', 'jane@example.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('Editor'))->toBeTrue();
});

it('validates new user input', function () {
    $this->actingAs($this->admin)
        ->post('/admin/users', [])
        ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'password']);
});

it('updates a user and syncs roles', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($this->admin)->put("/admin/users/{$user->id}", [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => $user->email,
        'roles' => ['Editor'],
    ])->assertRedirect('/admin/users');

    $user->refresh();
    expect($user->first_name)->toBe('Updated')
        ->and($user->hasRole('Editor'))->toBeTrue()
        ->and($user->hasRole('User'))->toBeFalse();
});

it('deletes a user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/users/{$user->id}")
        ->assertRedirect('/admin/users');

    expect(User::find($user->id))->toBeNull();
});

it('prevents deleting own account', function () {
    $this->actingAs($this->admin)
        ->delete("/admin/users/{$this->admin->id}")
        ->assertRedirect();

    expect(User::find($this->admin->id))->not->toBeNull();
});
