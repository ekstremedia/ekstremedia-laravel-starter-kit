<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('renders the activity log index with no filters', function () {
    $this->actingAs($this->admin)
        ->get('/admin/activity')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/ActivityLog/Index')
            ->has('activities.data')
            ->has('users')
            ->has('logNames')
            ->has('events')
        );
});

it('filters activity log by user', function () {
    $other = User::factory()->create();

    Activity::create(['log_name' => 'user', 'description' => 'a', 'causer_id' => $this->admin->id, 'causer_type' => User::class]);
    Activity::create(['log_name' => 'user', 'description' => 'b', 'causer_id' => $other->id, 'causer_type' => User::class]);

    $this->actingAs($this->admin)
        ->get('/admin/activity?user_id='.$this->admin->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activities.data', fn ($rows) => collect($rows)->count() === 1
                && collect($rows)->first()['description'] === 'a')
        );
});

it('filters activity log by log name', function () {
    Activity::create(['log_name' => 'auth', 'description' => 'login']);
    Activity::create(['log_name' => 'user', 'description' => 'updated']);

    $this->actingAs($this->admin)
        ->get('/admin/activity?log_name=auth')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activities.data', fn ($rows) => collect($rows)->count() === 1)
        );
});

it('filters activity log by date range', function () {
    $old = Activity::create(['log_name' => 'datefilter', 'description' => 'old']);
    DB::table('activity_log')->where('id', $old->id)->update(['created_at' => now()->subDays(10)]);
    Activity::create(['log_name' => 'datefilter', 'description' => 'recent']);

    $this->actingAs($this->admin)
        ->get('/admin/activity?date_from='.now()->subDays(2)->toDateString().'&log_name=datefilter')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activities.data', fn ($rows) => collect($rows)->count() === 1
                && collect($rows)->first()['description'] === 'recent')
        );
});

it('rejects invalid filter payloads', function () {
    $this->actingAs($this->admin)
        ->get('/admin/activity?user_id=not-a-number')
        ->assertSessionHasErrors('user_id');
});
