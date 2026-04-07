<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RoleAndPermissionSeeder::class);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => env('STARTER_ADMIN_EMAIL', 'admin@example.test')],
            [
                'first_name' => env('STARTER_ADMIN_FIRST_NAME', 'Admin'),
                'last_name' => env('STARTER_ADMIN_LAST_NAME', 'User'),
                'password' => bcrypt(env('STARTER_ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole('Admin');
    }
}
