<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves the {customer} route parameter (a slug) into a Tenant (our Customer),
 * verifies the authenticated user belongs to it, and boots tenancy (PG
 * search_path → tenant<id>).
 *
 * Admins bypass the membership check — they can enter any customer.
 *
 * Class name mentions "Tenancy" because stancl/tenancy is the underlying
 * mechanism; at the app layer we surface everything as "customer".
 */
class InitializeTenancyByPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if (! $route) {
            throw new NotFoundHttpException;
        }

        $slug = $route->parameter('customer');

        if (! is_string($slug) || $slug === '') {
            throw new NotFoundHttpException;
        }

        /** @var Tenant|null $customer */
        $customer = Tenant::query()->where('slug', $slug)->first();

        if (! $customer) {
            throw new NotFoundHttpException("Customer [{$slug}] not found.");
        }

        if ($customer->status !== 'active') {
            throw new AccessDeniedHttpException("Customer [{$slug}] is not active.");
        }

        $user = Auth::user();

        if (! $user || (! $user->hasRole('Admin') && ! $user->belongsToCustomer($customer))) {
            throw new AccessDeniedHttpException("You are not a member of [{$slug}].");
        }

        tenancy()->initialize($customer);

        $route->forgetParameter('customer');

        $request->attributes->set('customer', $customer);

        return $next($request);
    }
}
