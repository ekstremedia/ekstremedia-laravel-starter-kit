<?php

declare(strict_types=1);

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Pinned to the central connection — see App\Models\Permission for rationale.
 */
class Role extends SpatieRole
{
    public function getConnectionName(): ?string
    {
        return (string) config('tenancy.database.central_connection', 'central');
    }
}
