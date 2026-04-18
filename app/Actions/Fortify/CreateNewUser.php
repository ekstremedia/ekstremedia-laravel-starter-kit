<?php

namespace App\Actions\Fortify;

use App\Models\AppSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $settings = AppSetting::current();

        try {
            $user->assignRole($settings->default_role ?? 'User');
        } catch (RoleDoesNotExist) {
            // Configured default role isn't seeded; the account is created without a role.
        }

        if ($settings->send_welcome_notification) {
            $user->notify(new WelcomeNotification);
        }

        $this->attachToDefaultCustomer($user);

        return $user;
    }

    /**
     * When multi-tenancy is enabled, new sign-ups auto-join the default customer
     * configured in `tenancy.default_customer_slug` (env: `TENANCY_DEFAULT_CUSTOMER`).
     * In single-tenant mode this is a no-op. When the configured slug exists but
     * the row hasn't been seeded we log a warning — the user will hit 403 on
     * every tenant route until an admin attaches them, which is worth surfacing.
     */
    private function attachToDefaultCustomer(User $user): void
    {
        if (! config('tenancy.enabled')) {
            return;
        }

        $slug = config('tenancy.default_customer_slug', 'default');

        $customer = Tenant::query()->where('slug', $slug)->first();

        if ($customer !== null) {
            $user->customers()->syncWithoutDetaching([$customer->id]);

            return;
        }

        Log::warning('Default customer not found for new user; skipping auto-join.', [
            'slug' => $slug,
            'user_id' => $user->id,
        ]);
    }
}
