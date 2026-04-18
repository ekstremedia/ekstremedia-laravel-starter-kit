<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
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
            ->with('success', "Customer \"{$customer->name}\" created.");
    }

    public function edit(Tenant $customer): Response
    {
        $customer->load(['users' => function ($q) {
            $q->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->orderBy('users.email');
        }]);

        return Inertia::render('Admin/Customers/Edit', [
            'customer' => [
                'id' => $customer->id,
                'slug' => $customer->slug,
                'name' => $customer->name,
                'status' => $customer->status,
                'users' => $customer->users->map(fn (User $user) => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->fullName(),
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, Tenant $customer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $customer->update($data);

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Tenant $customer): RedirectResponse
    {
        $name = $customer->name;

        // Triggers the TenantDeleted job pipeline → drops the tenant<id> schema.
        $customer->delete();

        return redirect()
            ->route('admin.customers.index')
            ->with('success', "Customer \"{$name}\" deleted.");
    }

    public function attachMember(Request $request, Tenant $customer): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        $customer->users()->syncWithoutDetaching([$user->id]);

        return back()->with('success', "Added {$user->email} to {$customer->name}.");
    }

    public function detachMember(Tenant $customer, User $user): RedirectResponse
    {
        $customer->users()->detach($user->id);

        return back()->with('success', "Removed {$user->email} from {$customer->name}.");
    }
}
