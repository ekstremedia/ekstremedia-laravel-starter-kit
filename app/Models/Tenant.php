<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $status
 * @property bool $files_feature_enabled
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    /** @use HasFactory<TenantFactory> */
    use HasDatabase, HasFactory;

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Columns stored as real DB columns (not inside the `data` JSON blob).
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'slug', 'name', 'status', 'files_feature_enabled'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'files_feature_enabled' => 'boolean',
        ];
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
