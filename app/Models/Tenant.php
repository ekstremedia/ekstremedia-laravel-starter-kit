<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $status
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Columns stored as real DB columns (not inside the `data` JSON blob).
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'slug', 'name', 'status'];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withTimestamps();
    }
}
