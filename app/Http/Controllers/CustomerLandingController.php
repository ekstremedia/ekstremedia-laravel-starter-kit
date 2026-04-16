<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Post-login landing (`/app`). Central redirects from Fortify, LoginResponse,
 * RedirectIfAuthenticated, impersonation, and DevLogin all point here:
 *
 *   - tenancy disabled → redirect to `/dashboard` (plain single-tenant app)
 *   - tenancy enabled, user has 1 customer  → /c/{slug}/dashboard
 *   - tenancy enabled, user has many         → render the picker
 */
class CustomerLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if (! config('tenancy.enabled')) {
            return redirect('/dashboard');
        }

        $user = $request->user();

        // Admins can enter any active customer; regular users only their memberships.
        /** @var Collection<int, Tenant> $customers */
        $customers = $user->hasRole('Admin')
            ? Tenant::query()->where('status', 'active')->orderBy('name')->get()
            : $user->customers()->where('status', 'active')->orderBy('name')->get();

        if ($customers->count() === 1) {
            /** @var Tenant $only */
            $only = $customers->first();

            return redirect()->route('customer.dashboard', ['customer' => $only->slug]);
        }

        return $this->picker($customers);
    }

    /**
     * @param  Collection<int, Tenant>  $customers
     */
    private function picker($customers): Response
    {
        return Inertia::render('Customers/Picker', [
            'customers' => $customers->map(fn (Tenant $customer) => [
                'id' => $customer->id,
                'slug' => $customer->slug,
                'name' => $customer->name,
            ])->values(),
        ]);
    }
}
