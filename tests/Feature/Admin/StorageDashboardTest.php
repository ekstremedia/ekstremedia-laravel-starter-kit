<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('guards the dashboard behind the Admin role', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->get('/admin/storage')
        ->assertForbidden();
});

it('renders the storage dashboard for admins', function () {
    $this->actingAs($this->admin)
        ->get('/admin/storage')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Storage')
            ->has('totals.bytes')
            ->has('by_type')
            ->has('top_users')
            ->has('growth'));
});

it('updates a user quota via the admin endpoint', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->patch("/admin/users/{$target->id}/quota", ['storage_quota_bytes' => 5_000_000])
        ->assertRedirect();

    expect($target->fresh()->settings()->resolved()['storage_quota_bytes'])->toBe(5_000_000);
});
