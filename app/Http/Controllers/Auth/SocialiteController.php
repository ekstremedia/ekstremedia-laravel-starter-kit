<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * OAuth login bridge (Google / GitHub / ...).
 *
 * The whole controller only participates when `socialite.enabled` is true and
 * the specific provider is toggled in `socialite.providers.{name}`. That's
 * enforced by the `Auth\SocialiteEnabled` middleware applied to the routes.
 */
class SocialiteController extends Controller
{
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        $this->assertProviderAllowed($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->assertProviderAllowed($provider);

        /** @var SocialiteUser $oauthUser */
        $oauthUser = Socialite::driver($provider)->user();

        $user = $this->resolveUser($provider, $oauthUser);

        Auth::login($user, remember: true);
        activity('auth')->causedBy($user)->event('oauth_login')->withProperties(['provider' => $provider])->log('Signed in via '.$provider);

        return redirect()->intended(route('app.landing'));
    }

    /**
     * Find-or-create the local user.
     *
     * Matching rules:
     *   1) an existing user with the same (provider, provider_id) wins;
     *   2) else, a user with the provider email is adopted and linked;
     *   3) else, a brand-new account is created with a random password.
     *
     * The provider only links when its email is verified — otherwise a
     * malicious user could claim an account by spoofing an unverified email
     * on a fresh OAuth app.
     */
    private function resolveUser(string $provider, SocialiteUser $oauthUser): User
    {
        $email = $oauthUser->getEmail();
        $providerId = (string) $oauthUser->getId();

        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($user) {
            $user->forceFill([
                'provider_avatar_url' => $oauthUser->getAvatar(),
                'last_login_at' => now(),
            ])->save();

            return $user;
        }

        if ($email !== null) {
            $existing = User::where('email', $email)->first();
            if ($existing !== null) {
                $existing->forceFill([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'provider_avatar_url' => $oauthUser->getAvatar(),
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                    'last_login_at' => now(),
                ])->save();

                return $existing;
            }
        }

        [$first, $last] = $this->splitName((string) $oauthUser->getName(), (string) $oauthUser->getNickname());

        // forceFill so the provider_* columns bypass the strict $fillable list
        // on the User model — they're controller-resolved, never user-input.
        $new = new User;
        $new->forceFill([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email ?? $providerId.'@'.$provider.'.oauth.local',
            'password' => bcrypt(Str::random(40)),
            'provider' => $provider,
            'provider_id' => $providerId,
            'provider_avatar_url' => $oauthUser->getAvatar(),
            'email_verified_at' => $email !== null ? now() : null,
            'last_login_at' => now(),
        ])->save();

        return $new;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName, string $nickname): array
    {
        $name = trim($fullName) !== '' ? $fullName : $nickname;
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        $first = (string) array_shift($parts);
        $last = implode(' ', $parts);

        return [$first !== '' ? $first : 'User', $last];
    }

    private function assertProviderAllowed(string $provider): void
    {
        abort_unless(
            config('socialite.enabled') && (bool) config('socialite.providers.'.$provider, false),
            404,
        );
    }
}
