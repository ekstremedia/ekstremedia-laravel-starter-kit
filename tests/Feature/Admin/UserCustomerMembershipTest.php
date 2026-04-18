<?php

use App\Models\User;
use App\Notifications\CustomerMemberAddedNotification;
use App\Notifications\CustomerMemberRemovedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('tenancy.enabled', true);
    $this->seed(RoleAndPermissionSeeder::class);
});

it('shows customers on user show page when tenancy enabled', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create();
    $customer = createCustomer();
    joinCustomer($user, $customer);

    $this->actingAs($admin)
        ->get("/admin/users/{$user->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tenancy_enabled', true)
            ->has('user.customers', 1)
            ->where('user.customers.0.slug', $customer->slug)
        );
});

it('shows customers and all_customers on user edit page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create();
    $customer = createCustomer();
    joinCustomer($user, $customer);

    $this->actingAs($admin)
        ->get("/admin/users/{$user->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tenancy_enabled', true)
            ->has('user.customers', 1)
            ->has('all_customers', 1)
        );
});

it('allows admin to attach a customer to a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create();
    $customer = createCustomer();

    $this->actingAs($admin)
        ->post("/admin/users/{$user->id}/customers", [
            'customer_id' => $customer->id,
            'notify' => false,
        ])
        ->assertRedirect();

    expect($user->fresh()->customers()->count())->toBe(1);
});

it('allows admin to detach a customer from a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create();
    $customer = createCustomer();
    joinCustomer($user, $customer);

    $this->actingAs($admin)
        ->delete("/admin/users/{$user->id}/customers/{$customer->id}", [
            'notify' => false,
        ])
        ->assertRedirect();

    expect($user->fresh()->customers()->count())->toBe(0);
});

it('dispatches notification when notify is true on attach', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create();
    $customer = createCustomer();

    $this->actingAs($admin)
        ->post("/admin/users/{$user->id}/customers", [
            'customer_id' => $customer->id,
            'notify' => true,
        ])
        ->assertRedirect();

    Notification::assertSentTo($user, CustomerMemberAddedNotification::class);
});

it('does not dispatch notification when notify is false', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create();
    $customer = createCustomer();

    $this->actingAs($admin)
        ->post("/admin/users/{$user->id}/customers", [
            'customer_id' => $customer->id,
            'notify' => false,
        ])
        ->assertRedirect();

    Notification::assertNotSentTo($user, CustomerMemberAddedNotification::class);
});

it('dispatches removal notification when notify is true on detach', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create();
    $customer = createCustomer();
    joinCustomer($user, $customer);

    $this->actingAs($admin)
        ->delete("/admin/users/{$user->id}/customers/{$customer->id}", [
            'notify' => true,
        ])
        ->assertRedirect();

    Notification::assertSentTo($user, CustomerMemberRemovedNotification::class);
});

it('rejects non-admin from attaching customers', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $target = User::factory()->create();
    $customer = createCustomer();

    $this->actingAs($user)
        ->post("/admin/users/{$target->id}/customers", [
            'customer_id' => $customer->id,
            'notify' => false,
        ])
        ->assertForbidden();
});
