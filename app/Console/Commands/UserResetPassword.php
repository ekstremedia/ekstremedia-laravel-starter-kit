<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UserResetPassword extends Command
{
    protected $signature = 'user:reset-password {email} {--password= : Leave empty to auto-generate}';

    protected $description = 'Reset a user\'s password from the CLI.';

    public function handle(): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?? bin2hex(random_bytes(8)));
        $user->forceFill(['password' => Hash::make($password)])->save();

        $this->info("Password reset for {$user->email}.");
        if (! $this->option('password')) {
            $this->warn("Temporary password: {$password}");
        }

        return self::SUCCESS;
    }
}
