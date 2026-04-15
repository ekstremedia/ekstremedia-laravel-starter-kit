<?php

namespace Database\Seeders;

use App\Models\User;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin (from .env)
        $admin = User::firstOrCreate(
            ['email' => env('STARTER_ADMIN_EMAIL', 'admin@example.test')],
            [
                'first_name' => env('STARTER_ADMIN_FIRST_NAME', 'Admin'),
                'last_name' => env('STARTER_ADMIN_LAST_NAME', 'User'),
                'password' => Hash::make(env('STARTER_ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ],
        );
        $admin->assignRole('Admin');

        if (! env('SEED_DEMO_USERS', false)) {
            return;
        }

        $faker = Faker::create('nb_NO');
        $password = Hash::make('password');

        $this->seedRole($faker, 'Editor', 3, $password);
        $this->seedRole($faker, 'User', 8, $password);

        // One unverified user so devs can exercise the verify flow
        $unverified = User::firstOrCreate(
            ['email' => 'unverified@example.test'],
            [
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'password' => $password,
                'email_verified_at' => null,
            ],
        );
        $unverified->assignRole('User');
    }

    private function seedRole(Generator $faker, string $role, int $count, string $password): void
    {
        for ($i = 0; $i < $count; $i++) {
            $first = $faker->firstName();
            $last = $faker->lastName();
            $slug = Str::lower(Str::ascii($first).'.'.Str::ascii($last));
            $email = $slug.'@example.test';

            // Avoid collisions on duplicate name pairs
            $suffix = 1;
            while (User::where('email', $email)->exists()) {
                $email = $slug.($suffix++).'@example.test';
            }

            $user = User::create([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($role);
        }
    }
}
