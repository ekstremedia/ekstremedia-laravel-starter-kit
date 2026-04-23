<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('reads SuperAdmin purely from the is_super_admin column', function () {
    $user = User::factory()->create();
    expect($user->isSuperAdmin())->toBeFalse();

    $user->forceFill(['is_super_admin' => true])->save();
    expect($user->fresh()->isSuperAdmin())->toBeTrue();
});

it('SuperAdmin check does not depend on current team context', function () {
    $super = makeSuperAdmin(User::factory()->create());
    $customer = createCustomer();

    app(PermissionRegistrar::class)->setPermissionsTeamId($customer->id);
    expect($super->isSuperAdmin())->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    expect($super->isSuperAdmin())->toBeTrue();
});

it('the same user can hold different roles on different customers', function () {
    $user = User::factory()->create();
    $a = createCustomer('a', 'A');
    $b = createCustomer('b', 'B');

    grantRoleOnCustomer($user, 'Admin', $a);
    grantRoleOnCustomer($user, 'User', $b);

    $registrar = app(PermissionRegistrar::class);

    $registrar->setPermissionsTeamId($a->id);
    expect($user->fresh()->hasRole('Admin'))->toBeTrue();
    expect($user->fresh()->hasRole('User'))->toBeFalse();

    $registrar->setPermissionsTeamId($b->id);
    expect($user->fresh()->hasRole('User'))->toBeTrue();
    expect($user->fresh()->hasRole('Admin'))->toBeFalse();
});

it('InitializeTenancyByPath sets the permission team id to the active customer', function () {
    $customer = createCustomer();
    $user = User::factory()->create();
    grantRoleOnCustomer($user, 'User', $customer);

    // Clear team context to prove the middleware is what sets it.
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    $this->actingAs($user)
        ->get(customerUrl($customer, '/dashboard'))
        ->assertOk();

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($customer->id);
});

it('lets SuperAdmin enter any customer regardless of membership', function () {
    $super = makeSuperAdmin(User::factory()->create());
    $customer = createCustomer();

    // No membership on the customer; the SuperAdmin flag alone should bypass
    // the InitializeTenancyByPath membership guard.
    $this->actingAs($super)
        ->get(customerUrl($customer, '/dashboard'))
        ->assertOk();
});

it('blocks a non-SuperAdmin from a customer they are not a member of', function () {
    $outsider = User::factory()->create();
    $customer = createCustomer();

    $this->actingAs($outsider)
        ->get(customerUrl($customer, '/dashboard'))
        ->assertForbidden();
});

it('shares is_super_admin as a prop on customer-scoped pages', function () {
    $super = makeSuperAdmin(User::factory()->create());
    $customer = createCustomer();

    $this->actingAs($super)
        ->get(customerUrl($customer, '/dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.user.is_super_admin', true));
});
