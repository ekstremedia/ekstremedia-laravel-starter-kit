<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CustomerMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * Customer-scoped user management. A customer-Admin (i.e. someone who holds
 * the `Admin` role for the active customer) manages their own workspace's
 * members here — invite existing users, change their customer-level role,
 * remove them. Platform administration stays at `/admin/*` behind SuperAdmin.
 *
 * All lookups and mutations are implicitly scoped to the active customer via
 * `InitializeTenancyByPath` (which has already set the permission team id and
 * exposed the customer on the request).
 */
class CustomerMembersController extends Controller
{
    public function index(Request $request): Response
    {
        $customer = $this->customer($request);

        $members = $customer->users()
            ->orderBy('users.email')
            ->get(['users.id', 'users.first_name', 'users.last_name', 'users.email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'full_name' => $u->fullName(),
                'email' => $u->email,
                'roles' => CustomerMembership::rolesOn($u, $customer),
            ])
            ->values();

        return Inertia::render('Customer/Members/Index', [
            'members' => $members,
            'assignable_roles' => CustomerMembership::assignableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(CustomerMembership::assignableRoles())],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        CustomerMembership::attach($user, $customer, $data['roles']);

        return back()->with('success', __('flash.customers.member_added', ['email' => $user->email, 'name' => $customer->name]));
    }

    public function setRole(Request $request, User $user): RedirectResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in(CustomerMembership::assignableRoles())],
        ]);

        if (! $user->belongsToCustomer($customer)) {
            return back()->with('error', "{$user->email} is not a member of {$customer->name}.");
        }

        $newRoles = array_values(array_unique($data['roles']));
        $wasAdmin = in_array('Admin', CustomerMembership::rolesOn($user, $customer), true);
        $willBeAdmin = in_array('Admin', $newRoles, true);

        // Per-customer last-admin guard: don't let the only Admin on this
        // customer lose the role, whether by self-demote or external change.
        if ($wasAdmin && ! $willBeAdmin && $this->otherAdmins($customer, $user) === 0) {
            return back()->with('error', __('flash.customers.last_admin'));
        }

        CustomerMembership::syncRoles($user, $customer, $newRoles);

        return back()->with('success', __('flash.customers.member_role_updated', [
            'email' => $user->email,
            'role' => empty($newRoles) ? __('admin.users.no_roles') : implode(', ', $newRoles),
        ]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $customer = $this->customer($request);

        if (! $user->belongsToCustomer($customer)) {
            return back()->with('error', "{$user->email} is not a member of {$customer->name}.");
        }

        if (in_array('Admin', CustomerMembership::rolesOn($user, $customer), true)
            && $this->otherAdmins($customer, $user) === 0
        ) {
            return back()->with('error', __('flash.customers.last_admin'));
        }

        CustomerMembership::detach($user, $customer);

        return back()->with('success', __('flash.customers.member_removed', ['email' => $user->email, 'name' => $customer->name]));
    }

    /**
     * Count Admins on `$customer` excluding `$excluding` — used by the
     * last-admin guard on role-change and remove.
     */
    private function otherAdmins(Tenant $customer, User $excluding): int
    {
        return $customer->users()
            ->where('users.id', '!=', $excluding->id)
            ->get()
            ->filter(fn (User $m) => in_array('Admin', CustomerMembership::rolesOn($m, $customer), true))
            ->count();
    }

    private function customer(Request $request): Tenant
    {
        /** @var Tenant $customer */
        $customer = $request->attributes->get('customer');

        // Defensive: the team id should already be set by InitializeTenancyByPath,
        // but re-asserting keeps this controller safe if it's ever called from
        // a flow that bypasses that middleware (e.g. a console runner).
        app(PermissionRegistrar::class)->setPermissionsTeamId($customer->id);

        return $customer;
    }
}
