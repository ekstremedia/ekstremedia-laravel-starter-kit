<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('grants viewHorizon to admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue();
});

it('denies viewHorizon to non-admins', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});

it('grants viewPulse to admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    expect(Gate::forUser($admin)->allows('viewPulse'))->toBeTrue();
});

it('denies viewPulse to non-admins', function () {
    $user = User::factory()->create();
    $user->assignRole('Editor');

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeFalse();
});
