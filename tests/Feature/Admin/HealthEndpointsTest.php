<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('exposes queue-last as JSON with current cache state', function () {
    Cache::put('health:queue:last', ['nonce' => 'n1', 'at' => '2026-01-01T00:00:00Z']);

    $this->actingAs($this->admin)
        ->getJson('/admin/health/queue-last')
        ->assertOk()
        ->assertJson(['last' => ['nonce' => 'n1', 'at' => '2026-01-01T00:00:00Z']]);
});

it('returns null last when no ping has been recorded', function () {
    Cache::forget('health:queue:last');

    $this->actingAs($this->admin)
        ->getJson('/admin/health/queue-last')
        ->assertOk()
        ->assertJson(['last' => null]);
});

it('forbids non-admins from health endpoints', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)->post('/admin/health/queue')->assertForbidden();
    $this->actingAs($user)->post('/admin/health/broadcast')->assertForbidden();
    $this->actingAs($user)->get('/admin/health/queue-last')->assertForbidden();
});
