<?php

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->customer = createCustomer();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    joinCustomer($this->admin, $this->customer);
});

it('renders the app settings page with current values', function () {
    $this->actingAs($this->admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/AppSettings')
            ->has('settings.site_up')
            ->has('settings.registration_open')
            ->has('roles'));
});

it('saves app settings and logs the change', function () {
    $this->actingAs($this->admin)->patch('/admin/settings', [
        'site_up' => false,
        'registration_open' => false,
        'login_enabled' => false,
        'require_email_verification' => true,
        'default_role' => 'User',
        'require_2fa_for_admins' => true,
        'send_welcome_notification' => false,
        'maintenance_message' => 'Back soon',
        'announcement_banner' => 'Friday maintenance',
        'announcement_severity' => 'warn',
    ])->assertRedirect();

    $s = AppSetting::current();
    expect($s->site_up)->toBeFalse()
        ->and($s->registration_open)->toBeFalse()
        ->and($s->announcement_banner)->toBe('Friday maintenance');
});

it('shows maintenance page to non-admins when site is down', function () {
    AppSetting::current()->update(['site_up' => false]);

    $user = User::factory()->create();
    $user->assignRole('User');
    joinCustomer($user, $this->customer);

    $this->actingAs($user)
        ->get(customerUrl($this->customer, '/dashboard'))
        ->assertStatus(503)
        ->assertInertia(fn ($page) => $page->component('Maintenance'));
});

it('lets admins bypass maintenance mode', function () {
    AppSetting::current()->update(['site_up' => false]);

    $this->actingAs($this->admin)
        ->get(customerUrl($this->customer, '/dashboard'))
        ->assertOk();
});

it('blocks the register page when registration is closed', function () {
    AppSetting::current()->update(['registration_open' => false]);

    $this->get('/register')
        ->assertStatus(403)
        ->assertInertia(fn ($page) => $page->component('Auth/RegistrationClosed'));
});

it('shares the announcement banner via Inertia', function () {
    AppSetting::current()->update([
        'announcement_banner' => 'Read-only mode',
        'announcement_severity' => 'info',
    ]);

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->where('app_settings.announcement.text', 'Read-only mode')
            ->where('app_settings.announcement.severity', 'info'));
});
