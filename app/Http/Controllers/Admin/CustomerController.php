<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StorageUsageService;
use App\Support\CustomerMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landlord CRUD for customers.
 *
 * NOTE: the underlying model is `App\Models\Tenant` (extending stancl/tenancy's
 * base `Tenant` model). "Customer" is our user-facing name for the same row.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $customers = Tenant::query()
            ->withCount('users')
            ->when($search !== '', function ($q) use ($search) {
                $escaped = addcslashes(mb_strtolower($search), '%_\\');
                $needle = '%'.$escaped.'%';
                $q->where(function ($q) use ($needle) {
                    $q->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$needle])
                        ->orWhereRaw("LOWER(slug) LIKE ? ESCAPE '\\'", [$needle]);
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Customers/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('tenants', 'slug')],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);

        // The request-level rules above only fire when the client sent a slug.
        // When we fall back to `Str::slug($name)` an odd name can produce an
        // empty string, an over-length value, or collide with an existing
        // customer — re-run the same rules against the resolved slug so the
        // auto-generated branch can't bypass them.
        Validator::make(['slug' => $slug], [
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('tenants', 'slug')],
        ], [
            'slug.*' => 'Could not derive a valid slug from the name; please provide one explicitly.',
        ])->validate();

        $customer = Tenant::create([
            'name' => $data['name'],
            'slug' => $slug,
            'status' => 'active',
        ]);

        return redirect()
            ->route('admin.customers.edit', $customer)
            ->with('success', __('flash.customers.created', ['name' => $customer->name]));
    }

    public function edit(Tenant $customer, StorageUsageService $usage): Response
    {
        $customer->load(['users' => function ($q) {
            $q->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->orderBy('users.email');
        }]);

        // Live usage for the "Used: X GB of Y" caption on the admin edit
        // page. Cheap per-page query; surface the fresh number rather than
        // the denormalized column in case it drifted.
        $companyUsed = $usage->usedBytesForTenantCompany($customer);

        return Inertia::render('Admin/Customers/Edit', [
            'customer' => [
                'id' => $customer->id,
                'slug' => $customer->slug,
                'name' => $customer->name,
                'status' => $customer->status,
                'files_feature_enabled' => (bool) $customer->files_feature_enabled,
                'company_files_enabled' => (bool) $customer->company_files_enabled,
                'storage_quota_bytes' => $customer->storage_quota_bytes,
                'storage_used_bytes' => $companyUsed,
                'default_member_storage_bytes' => $customer->default_member_storage_bytes,
                'users' => $customer->users->map(fn (User $user) => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->fullName(),
                ])->values(),
            ],
            'global_files_feature_enabled' => (bool) AppSetting::current()->files_feature_enabled,
            'global_default_personal_storage_bytes' => AppSetting::current()->default_personal_storage_bytes,
        ]);
    }

    public function update(Request $request, Tenant $customer): RedirectResponse
    {
        $globalFilesEnabled = (bool) AppSetting::current()->files_feature_enabled;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'files_feature_enabled' => [
                'sometimes',
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail) use ($globalFilesEnabled): void {
                    if ($value && ! $globalFilesEnabled) {
                        $fail('Files feature is disabled globally in App Settings — enable it there first.');
                    }
                },
            ],
            'company_files_enabled' => ['sometimes', 'boolean'],
            // -1 = explicit unlimited, null = unlimited (no cap set),
            // 0 = blocked, N>0 = byte cap.
            'storage_quota_bytes' => ['sometimes', 'nullable', 'integer', 'min:-1'],
            'default_member_storage_bytes' => ['sometimes', 'nullable', 'integer', 'min:-1'],
        ]);

        // Company files requires the personal files feature to be on too —
        // disabling files_feature_enabled implicitly disables company files.
        $filesEnabled = array_key_exists('files_feature_enabled', $data)
            ? (bool) $data['files_feature_enabled']
            : (bool) $customer->files_feature_enabled;
        if (array_key_exists('company_files_enabled', $data) && $data['company_files_enabled'] && ! $filesEnabled) {
            $data['company_files_enabled'] = false;
        }

        $customer->update($data);

        return back()->with('success', __('flash.customers.updated'));
    }

    public function destroy(Tenant $customer): RedirectResponse
    {
        $name = $customer->name;

        // Triggers the TenantDeleted job pipeline → drops the tenant<id> schema.
        $customer->delete();

        return redirect()
            ->route('admin.customers.index')
            ->with('success', __('flash.customers.deleted', ['name' => $name]));
    }

    public function attachMember(Request $request, Tenant $customer): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(CustomerMembership::assignableRoles())],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        CustomerMembership::attach($user, $customer, $data['roles']);

        return back()->with('success', __('flash.customers.member_added', ['email' => $user->email, 'name' => $customer->name]));
    }

    public function detachMember(Tenant $customer, User $user): RedirectResponse
    {
        CustomerMembership::detach($user, $customer);

        return back()->with('success', __('flash.customers.member_removed', ['email' => $user->email, 'name' => $customer->name]));
    }
}
