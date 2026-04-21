<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * Dump everything we hold for one user as JSON.
 *
 * Intended for GDPR / CCPA "right to access" requests. The scope is
 * intentionally conservative: profile fields, roles, permissions, settings,
 * activity-log entries, and notifications. Extend `payload()` with
 * domain-specific relations (files, orders, ...) as your app grows.
 */
class UserExport extends Command
{
    protected $signature = 'user:export {email} {--out= : Write to this file instead of stdout}';

    protected $description = 'Export every piece of user-identifiable data we store for the given account.';

    public function handle(): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $json = (string) json_encode($this->payload($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $out = (string) ($this->option('out') ?? '');
        if ($out !== '') {
            // GDPR exports are pure PII. Lock the file, verify the write, and
            // clamp permissions to owner-only so a world-readable dump doesn't
            // end up on a shared host.
            $written = @file_put_contents($out, $json, LOCK_EX);
            if ($written === false) {
                $this->error("Failed to write export to {$out}.");

                return self::FAILURE;
            }
            if (! @chmod($out, 0o600)) {
                @unlink($out);
                $this->error("Failed to set 0600 permissions on {$out}; export aborted.");

                return self::FAILURE;
            }
            $this->info("Wrote export for {$user->email} to {$out}.");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'roles' => $user->roles()->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'settings' => $user->settings()->resolved(),
            'activity_as_causer' => Activity::query()
                ->where('causer_type', User::class)
                ->where('causer_id', $user->id)
                ->get(['id', 'log_name', 'event', 'description', 'created_at'])
                ->toArray(),
            'notifications' => $user->notifications()->get(['id', 'type', 'data', 'read_at', 'created_at'])->toArray(),
        ];
    }
}
