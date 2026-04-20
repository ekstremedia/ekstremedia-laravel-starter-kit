<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view dashboard',
            'manage users',
            'manage roles',
            'manage resources',
            'manage settings',
            'manage profile',
            'manage storage',
            // File manager — gate each mutation individually so admins can
            // carve out read-only roles by removing a subset of these.
            'upload files',
            'create folders',
            'rename files',
            'delete files',
            'share files',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->givePermissionTo(Permission::all());

        $editorRole = Role::firstOrCreate(['name' => 'Editor']);
        $editorRole->givePermissionTo([
            'view dashboard',
            'manage resources',
            'manage settings',
            'manage profile',
            'upload files',
            'create folders',
            'rename files',
            'delete files',
            'share files',
        ]);

        $userRole = Role::firstOrCreate(['name' => 'User']);
        $userRole->givePermissionTo([
            'view dashboard',
            'manage settings',
            'manage profile',
            'upload files',
            'create folders',
            'rename files',
            'delete files',
            'share files',
        ]);
    }
}
