<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserGrantRole extends Command
{
    protected $signature = 'user:grant-role {email} {role} {--revoke : Revoke the role instead of granting it}';

    protected $description = 'Grant (or revoke) a role on a user.';

    public function handle(): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $role = (string) $this->argument('role');

        if ($this->option('revoke')) {
            $user->removeRole($role);
            $this->info("Revoked {$role} from {$user->email}.");
        } else {
            $user->assignRole($role);
            $this->info("Granted {$role} to {$user->email}.");
        }

        return self::SUCCESS;
    }
}
