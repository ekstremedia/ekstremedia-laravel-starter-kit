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
        $this->call(RoleAndPermissionSeeder::class);

        // Admin (from .env)
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

        if (env('SEED_DEMO_USERS', false)) {
            // Editors
            $editor1 = User::firstOrCreate(
                ['email' => 'astrid@example.test'],
                [
                    'first_name' => 'Astrid',
                    'last_name' => 'Lindgren',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            );
            $editor1->assignRole('Editor');

            $editor2 = User::firstOrCreate(
                ['email' => 'erik@example.test'],
                [
                    'first_name' => 'Erik',
                    'last_name' => 'Solberg',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            );
            $editor2->assignRole('Editor');

            // Users
            $user1 = User::firstOrCreate(
                ['email' => 'ingrid@example.test'],
                [
                    'first_name' => 'Ingrid',
                    'last_name' => 'Haugen',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            );
            $user1->assignRole('User');

            $user2 = User::firstOrCreate(
                ['email' => 'lars@example.test'],
                [
                    'first_name' => 'Lars',
                    'last_name' => 'Henriksen',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            );
            $user2->assignRole('User');

            // Unverified user
            $user3 = User::firstOrCreate(
                ['email' => 'sigrid@example.test'],
                [
                    'first_name' => 'Sigrid',
                    'last_name' => 'Nygaard',
                    'password' => bcrypt('password'),
                    'email_verified_at' => null,
                ],
            );
            $user3->assignRole('User');
        }
    }
}
