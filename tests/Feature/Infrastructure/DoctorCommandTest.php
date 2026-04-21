<?php

use Database\Seeders\RoleAndPermissionSeeder;

it('passes on a freshly migrated + seeded database', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->artisan('starter:doctor')->assertSuccessful();
});

it('emits JSON with --json', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->artisan('starter:doctor --json')->assertSuccessful();
});
