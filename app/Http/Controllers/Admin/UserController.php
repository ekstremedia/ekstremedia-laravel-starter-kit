<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSetting;
use App\Notifications\AccountBannedNotification;
use App\Notifications\AdminTestNotification;
use App\Notifications\CustomerMemberAddedNotification;
use App\Notifications\CustomerMemberRemovedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $sort = $request->string('sort')->toString() ?: 'id';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        // Only allow sorting on a known allowlist; defaults to id desc.
        $allowedSort = ['id', 'first_name', 'last_name', 'email', 'storage_used_bytes'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }

        $users = User::query()
            ->with(['roles:id,name', 'media', 'setting'])
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
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        $users->getCollection()->transform(function (User $user) {
            $user->setAttribute('avatar_thumb_url', $user->avatarUrl('thumb'));
            // `setting` is eager-loaded above — reach through it directly so
            // each row doesn't hit firstOrCreate (N+1 across a 15-row page).
            $resolved = $user->setting
                ? array_merge(UserSetting::$defaults, $user->setting->settings ?? [])
                : UserSetting::$defaults;
            $user->setAttribute('storage_quota_bytes', $resolved['storage_quota_bytes'] ?? null);

            return $user;
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function setQuota(Request $request, User $user): RedirectResponse
    {
        // `nullable` allows unlimited; `0` is valid (disables uploads).
        $data = $request->validate([
            'storage_quota_bytes' => 'nullable|integer|min:0',
            'files_enabled' => 'sometimes|boolean',
        ]);

        $patch = [];
        if (array_key_exists('storage_quota_bytes', $data)) {
            $patch['storage_quota_bytes'] = $data['storage_quota_bytes'];
            // Reset alert tracking when quota moves, otherwise users who
            // already crossed 95/100 % under the old cap would silently stop
            // receiving alerts (the stored threshold would shadow the new one).
            $patch['storage_last_alerted_threshold'] = null;
        }
        if (array_key_exists('files_enabled', $data)) {
            $patch['files_enabled'] = (bool) $data['files_enabled'];
        }

        if ($patch === []) {
            return back();
        }

        $user->settings()->merge($patch);

        return back()->with('success', __('admin.users.quota_updated'));
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

        return redirect()->route('admin.users.index')->with('success', __('flash.users.created'));
    }

    public function show(User $user): Response
    {
        $tenancyEnabled = (bool) config('tenancy.enabled');

        $user->load('roles:id,name', 'media');

        if ($tenancyEnabled) {
            $user->load('customers');
        }

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
                'customers' => $tenancyEnabled
                    ? $user->customers()->orderBy('name')->get(['tenants.id', 'name', 'slug'])->toArray()
                    : [],
            ],
            'activity' => $recentActivity,
            'tenancy_enabled' => $tenancyEnabled,
        ]);
    }

    public function edit(User $user): Response
    {
        $tenancyEnabled = (bool) config('tenancy.enabled');

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'customers' => $tenancyEnabled
                    ? $user->customers()->orderBy('name')->get(['tenants.id', 'name', 'slug'])->toArray()
                    : [],
            ],
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'tenancy_enabled' => $tenancyEnabled,
            'all_customers' => $tenancyEnabled
                ? Tenant::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'slug'])->toArray()
                : [],
        ]);
    }

    public function verify(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();

            activity('user')->performedOn($user)->event('email_verified')
                ->log("Admin marked {$user->email} as verified");
        }

        return back()->with('success', __('flash.users.verified'));
    }

    public function unverify(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => null])->save();

            activity('user')->performedOn($user)->event('email_unverified')
                ->log("Admin cleared verification for {$user->email}");
        }

        return back()->with('success', __('flash.users.unverified'));
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

        return back()->with('success', __('flash.users.banned'));
    }

    public function unban(User $user): RedirectResponse
    {
        $user->unban();

        activity('user')->performedOn($user)->event('unbanned')
            ->log("Unbanned {$user->email}");

        return back()->with('success', __('flash.users.unbanned'));
    }

    public function resendVerification(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return back()->with('error', 'User is already verified.');
        }

        $user->sendEmailVerificationNotification();

        activity('user')->performedOn($user)->event('verification_resent')
            ->log("Resent verification email to {$user->email}");

        return back()->with('success', __('flash.users.verification_resent'));
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

        return back()->with('success', __('flash.users.twofa_reset'));
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

        return back()->with('success', __('flash.users.test_notification_sent'));
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

        return redirect()->route('admin.users.index')->with('success', __('flash.users.updated'));
    }

    public function attachCustomer(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => [
                Rule::exists('tenants', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'notify' => ['boolean'],
        ]);

        /** @var Collection<int, Tenant> $customers */
        $customers = Tenant::query()
            ->where('status', 'active')
            ->whereIn('id', $data['customer_ids'])
            ->get();

        $existingIds = $user->customers()->pluck('tenants.id')->all();
        $newCustomers = $customers->reject(fn (Tenant $c) => in_array($c->id, $existingIds, true));

        $user->customers()->syncWithoutDetaching($customers->pluck('id')->all());

        $newNames = [];
        foreach ($newCustomers as $customer) {
            /** @var Tenant $customer */
            $newNames[] = $customer->name;

            if ($data['notify'] ?? false) {
                $user->notify(new CustomerMemberAddedNotification($customer));
            }

            activity('user')
                ->performedOn($user)
                ->withProperties(['customer' => $customer->name, 'notify' => $data['notify'] ?? false])
                ->event('customer_attached')
                ->log("Added {$user->email} to {$customer->name}");
        }

        if ($newNames === []) {
            return back();
        }

        $names = implode(', ', $newNames);

        if (count($newNames) === 1) {
            return back()->with('success', __('flash.users.customer_attached', ['email' => $user->email, 'name' => $names]));
        }

        return back()->with('success', __('flash.users.customers_attached', ['email' => $user->email, 'names' => $names]));
    }

    public function detachCustomer(Request $request, User $user, Tenant $customer): RedirectResponse
    {
        $data = $request->validate([
            'notify' => ['boolean'],
        ]);

        $customerName = $customer->name;
        $detached = $user->customers()->detach($customer->id);

        if ($detached === 0) {
            return back()->with('error', "{$user->email} is not a member of {$customerName}.");
        }

        if ($data['notify'] ?? false) {
            $user->notify(new CustomerMemberRemovedNotification($customerName));
        }

        activity('user')
            ->performedOn($user)
            ->withProperties(['customer' => $customerName, 'notify' => $data['notify'] ?? false])
            ->event('customer_detached')
            ->log("Removed {$user->email} from {$customerName}");

        return back()->with('success', __('flash.users.customer_detached', ['email' => $user->email, 'name' => $customerName]));
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

        return redirect()->route('admin.users.index')->with('success', __('flash.users.deleted'));
    }
}
