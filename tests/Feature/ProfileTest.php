<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('redirects guests to login', function () {
    $this->get('/profile')->assertRedirect('/login');
});

it('redirects unverified users to verification notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertRedirect('/email/verify');
});

it('renders the profile page for verified users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Profile'));
});

it('updates profile information via fortify', function () {
    $user = User::factory()->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'email' => 'old@example.com',
    ]);

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'first_name' => 'New',
            'last_name' => 'Person',
            'email' => 'new@example.com',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->first_name)->toBe('New');
    expect($user->last_name)->toBe('Person');
    expect($user->email)->toBe('new@example.com');
});

it('updates password via fortify with correct current password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('current-password'),
    ]);

    $this->actingAs($user)
        ->put('/user/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('rejects password update with incorrect current password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('current-password'),
    ]);

    $this->actingAs($user)
        ->put('/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('current-password', $user->fresh()->password))->toBeTrue();
});
