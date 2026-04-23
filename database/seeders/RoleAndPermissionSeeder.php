<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the customer-scoped role templates.
 *
 * `Admin`, `Editor`, and `User` are role **templates**: the role rows
 * themselves carry `team_id = null` (they're definitions, not assignments),
 * and each per-customer assignment in `model_has_roles` stamps the customer
 * id as `team_id`. Every `hasRole`/`can` check then auto-scopes to whichever
 * customer is active.
 *
 * Platform-wide super-user access is NOT a role — it's a boolean flag on the
 * user row (`users.is_super_admin`). Spatie's team schema forces
 * `model_has_roles.team_id` to be non-null, so "global" role assignments
 * aren't representable there; seeding + checking SuperAdmin via a column
 * keeps that distinction clean.
 */
class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $customerPermissions = [
            'view dashboard',
            'manage customer users',     // invite / remove members of the active customer
            'manage customer settings',  // toggle customer-level feature flags
            'manage profile',
            // File manager — gate each mutation individually so customer-Admins
            // can carve out read-only roles by removing a subset of these.
            'upload files',
            'create folders',
            'rename files',
            'delete files',
            'share files',
        ];

        foreach ($customerPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Customer-scoped role templates. The role rows are team-agnostic;
        // the assignment in model_has_roles carries the team id.
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->syncPermissions($customerPermissions);

        $editorRole = Role::firstOrCreate(['name' => 'Editor']);
        $editorRole->syncPermissions([
            'view dashboard',
            'manage profile',
            'upload files',
            'create folders',
            'rename files',
            'delete files',
            'share files',
        ]);

        $userRole = Role::firstOrCreate(['name' => 'User']);
        $userRole->syncPermissions([
            'view dashboard',
            'manage profile',
            'upload files',
            'create folders',
            'rename files',
            'delete files',
            'share files',
        ]);
    }
}
