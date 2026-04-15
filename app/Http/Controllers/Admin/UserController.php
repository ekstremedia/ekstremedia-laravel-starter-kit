<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Notifications\AccountBannedNotification;
use App\Notifications\AdminTestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->with(['roles:id,name', 'media'])
            ->when($search !== '', function ($q) use ($search) {
                $driver = $q->getModel()->getConnection()->getDriverName();
                $op = $driver === 'pgsql' ? 'ilike' : 'like';
                $needle = '%'.mb_strtolower($search).'%';

                $q->where(function ($q) use ($op, $needle, $driver) {
                    if ($driver === 'pgsql') {
                        $q->where('email', $op, $needle)
                            ->orWhere('first_name', $op, $needle)
                            ->orWhere('last_name', $op, $needle);
                    } else {
                        $q->whereRaw('LOWER(email) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(first_name) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$needle]);
                    }
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $users->getCollection()->transform(function (User $user) {
            $user->setAttribute('avatar_thumb_url', $user->avatarUrl('thumb'));

            return $user;
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles($data['roles'] ?? []);

        activity('user')
            ->performedOn($user)
            ->withProperties(['roles' => $data['roles'] ?? []])
            ->event('created')
            ->log("Created user {$user->email}");

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function show(User $user): Response
    {
        $user->load('roles:id,name', 'media');

        $recentActivity = Activity::query()
            ->where(function ($q) use ($user) {
                $q->where('causer_id', $user->id)->where('causer_type', User::class)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('subject_id', $user->id)->where('subject_type', User::class);
                    });
            })
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'log_name' => $a->log_name,
                'description' => $a->description,
                'event' => $a->event,
                'created_at' => $a->created_at->toIso8601String(),
                'causer_id' => $a->causer_id,
            ]);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->fullName(),
                'email' => $user->email,
                'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
                'banned_at' => optional($user->banned_at)->toIso8601String(),
                'banned_reason' => $user->banned_reason,
                'last_login_at' => optional($user->last_login_at)->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'roles' => $user->roles->pluck('name')->toArray(),
                'avatar_url' => $user->avatarUrl('avatar'),
                'avatar_thumb_url' => $user->avatarUrl('thumb'),
                'unread_notifications_count' => $user->unreadNotifications()->count(),
            ],
            'activity' => $recentActivity,
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
            ],
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function verify(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();

            activity('user')->performedOn($user)->event('email_verified')
                ->log("Admin marked {$user->email} as verified");
        }

        return back()->with('success', 'Email marked as verified.');
    }

    public function unverify(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => null])->save();

            activity('user')->performedOn($user)->event('email_unverified')
                ->log("Admin cleared verification for {$user->email}");
        }

        return back()->with('success', 'Email verification cleared.');
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot ban yourself.');
        }
        if ($user->hasRole('Admin')) {
            return back()->with('error', 'You cannot ban another admin.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->ban($data['reason'] ?? null);
        $user->notify(new AccountBannedNotification($data['reason'] ?? null));

        activity('user')
            ->performedOn($user)
            ->withProperties(['reason' => $data['reason'] ?? null])
            ->event('banned')
            ->log("Banned {$user->email}");

        return back()->with('success', 'User banned.');
    }

    public function unban(User $user): RedirectResponse
    {
        $user->unban();

        activity('user')->performedOn($user)->event('unbanned')
            ->log("Unbanned {$user->email}");

        return back()->with('success', 'User unbanned.');
    }

    public function resendVerification(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return back()->with('error', 'User is already verified.');
        }

        $user->sendEmailVerificationNotification();

        activity('user')->performedOn($user)->event('verification_resent')
            ->log("Resent verification email to {$user->email}");

        return back()->with('success', 'Verification email sent.');
    }

    public function reset2fa(User $user): RedirectResponse
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        activity('user')->performedOn($user)->event('two_factor_reset')
            ->log("Reset 2FA for {$user->email}");

        return back()->with('success', '2FA has been reset for this user.');
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        activity('user')->performedOn($user)->event('password_reset_sent')
            ->log("Sent password reset link to {$user->email}");

        return back()->with(
            $status === Password::RESET_LINK_SENT ? 'success' : 'error',
            $status === Password::RESET_LINK_SENT
                ? 'Password reset link sent.'
                : 'Could not send reset link.',
        );
    }

    public function notifyTest(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $user->notify(new AdminTestNotification($data['message'] ?? 'Hello from the admin panel!'));

        activity('user')->performedOn($user)->event('test_notification_sent')
            ->log("Sent test notification to {$user->email}");

        return back()->with('success', 'Test notification sent.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $previousRoles = $user->getRoleNames()->sort()->values()->all();
        $newRoles = collect($data['roles'] ?? [])->sort()->values()->all();
        $passwordChanged = ! empty($data['password']);

        $user->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
        ]);

        if ($passwordChanged) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles($data['roles'] ?? []);

        if ($previousRoles !== $newRoles || $passwordChanged) {
            activity('user')
                ->performedOn($user)
                ->withProperties([
                    'roles_added' => array_values(array_diff($newRoles, $previousRoles)),
                    'roles_removed' => array_values(array_diff($previousRoles, $newRoles)),
                    'password_changed' => $passwordChanged,
                ])
                ->event('admin_updated')
                ->log("Admin updated user {$user->email}");
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $email = $user->email;
        $roles = $user->getRoleNames()->all();
        $user->delete();

        activity('user')
            ->withProperties(['email' => $email, 'roles' => $roles])
            ->event('deleted')
            ->log("Deleted user {$email}");

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
