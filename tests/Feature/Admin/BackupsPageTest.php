<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('renders the backups index for admins', function () {
    $this->actingAs($this->admin)
        ->get('/admin/backups')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Backups')
            ->has('backups')
            ->has('disks')
            ->has('name')
        );
});

it('queues a backup:run when the admin triggers a backup', function () {
    Bus::fake();

    $this->actingAs($this->admin)
        ->post('/admin/backups/run')
        ->assertRedirect();
});

it('forbids non-admins from backup management', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)->get('/admin/backups')->assertForbidden();
    $this->actingAs($user)->post('/admin/backups/run')->assertForbidden();
    $this->actingAs($user)->post('/admin/backups/clean')->assertForbidden();
});
