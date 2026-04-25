<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $headline
 * @property string|null $about
 * @property string|null $location
 * @property string|null $website
 * @property string $status
 * @property bool $files_feature_enabled
 * @property bool $company_files_enabled
 * @property int|null $storage_quota_bytes
 * @property int $storage_used_bytes
 * @property int|null $default_member_storage_bytes
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
        return [
            'id',
            'slug',
            'name',
            'headline',
            'about',
            'location',
            'website',
            'status',
            'files_feature_enabled',
            'company_files_enabled',
            'storage_quota_bytes',
            'storage_used_bytes',
            'default_member_storage_bytes',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'files_feature_enabled' => 'boolean',
            'company_files_enabled' => 'boolean',
            'storage_quota_bytes' => 'integer',
            'storage_used_bytes' => 'integer',
            'default_member_storage_bytes' => 'integer',
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

    /**
     * Native company-scope FileItems belonging to this tenant (folders + files
     * uploaded directly to the company area).
     *
     * @return HasMany<FileItem, $this>
     */
    public function companyFiles(): HasMany
    {
        return $this->hasMany(FileItem::class)->where('scope', FileItem::SCOPE_COMPANY);
    }

    /**
     * @return HasMany<CompanyFileLink, $this>
     */
    public function companyFileLinks(): HasMany
    {
        return $this->hasMany(CompanyFileLink::class);
    }
}
