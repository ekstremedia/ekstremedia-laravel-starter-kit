<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    // Spatie's BackupDestination writes into the real configured disk; swap it
    // for an in-memory fake so we can drop a placeholder zip and drive the UI
    // endpoints without creating real archives.
    Storage::fake('local');
    config(['backup.backup.destination.disks' => ['local']]);
});

it('rejects downloads for disks not in the backup config', function () {
    $this->actingAs($this->admin)
        ->get('/admin/backups/download?disk=public&path=fake.zip')
        ->assertNotFound();
});

it('rejects downloads for missing files', function () {
    $this->actingAs($this->admin)
        ->get('/admin/backups/download?disk=local&path=missing.zip')
        ->assertNotFound();
});

it('streams the backup file when the download path exists', function () {
    Storage::disk('local')->put('starter/2026-04-21-noon.zip', 'zip-bytes');

    $response = $this->actingAs($this->admin)
        ->get('/admin/backups/download?disk=local&path=starter%2F2026-04-21-noon.zip');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toContain('2026-04-21-noon.zip');
});

it('refuses to prepare a restore when the filename confirmation does not match', function () {
    Storage::disk('local')->put('starter/real.zip', 'x');

    $this->actingAs($this->admin)
        ->post('/admin/backups/prepare-restore', [
            'disk' => 'local',
            'path' => 'starter/real.zip',
            'confirm' => 'wrong.zip',
        ])
        ->assertStatus(422);
});

it('forbids non-admins from download and restore endpoints', function () {
    Storage::disk('local')->put('starter/real.zip', 'x');

    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->get('/admin/backups/download?disk=local&path=starter%2Freal.zip')
        ->assertForbidden();

    $this->actingAs($user)
        ->post('/admin/backups/prepare-restore', [
            'disk' => 'local', 'path' => 'starter/real.zip', 'confirm' => 'real.zip',
        ])
        ->assertForbidden();
});
