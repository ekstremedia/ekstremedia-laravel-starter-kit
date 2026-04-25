<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CustomerMembership;

class TenantProfilePolicy
{
    /**
     * Members of a customer can view that customer's profile. Super admins
     * see all.
     */
    public function view(User $viewer, Tenant $customer): bool
    {
        if ($viewer->isSuperAdmin()) {
            return true;
        }

        return $viewer->belongsToCustomer($customer);
    }

    /**
     * Customer Admins (a Spatie team-scoped role inside this customer) and
     * super admins may edit a customer's profile. Editor/User roles cannot.
     */
    public function update(User $viewer, Tenant $customer): bool
    {
        if ($viewer->isSuperAdmin()) {
            return true;
        }

        return in_array('Admin', CustomerMembership::rolesOn($viewer, $customer), true);
    }
}
