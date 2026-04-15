<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->target = User::factory()->create();
    $this->target->assignRole('User');
});

it('allows an admin to impersonate a non-admin', function () {
    $this->actingAs($this->admin)
        ->post("/admin/users/{$this->target->id}/impersonate")
        ->assertRedirect('/dashboard');

    expect(session('impersonated_by'))->not->toBeNull();
    expect(auth()->id())->toBe($this->target->id);
});

it('forbids impersonating another admin', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('Admin');

    $this->actingAs($this->admin)
        ->post("/admin/users/{$otherAdmin->id}/impersonate")
        ->assertForbidden();
});

it('forbids non-admins from impersonating', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->post("/admin/users/{$this->target->id}/impersonate")
        ->assertForbidden();
});

it('can leave impersonation and return to the original admin', function () {
    $this->actingAs($this->admin)->post("/admin/users/{$this->target->id}/impersonate");

    // Now we're logged in as target with impersonator set
    $this->post('/impersonate/leave')
        ->assertRedirect('/admin/users');

    expect(auth()->id())->toBe($this->admin->id);
});

it('logs impersonation events to the activity log', function () {
    $this->actingAs($this->admin)
        ->post("/admin/users/{$this->target->id}/impersonate");

    $log = \Spatie\Activitylog\Models\Activity::where('log_name', 'impersonation')->latest()->first();
    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('started');
});
