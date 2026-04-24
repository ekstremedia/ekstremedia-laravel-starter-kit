<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $type
 * @property string $scope
 * @property string $name
 * @property string|null $mime_type
 * @property int $size
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read User $user
 * @property-read FileItem|null $parent
 */
class FileItem extends Model implements HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    public const TYPE_FOLDER = 'folder';

    public const TYPE_FILE = 'file';

    public const SCOPE_PERSONAL = 'personal';

    public const SCOPE_COMPANY = 'company';

    public const IMAGE_SIZES = [
        'thumb' => ['width' => 400, 'height' => 400, 'quality' => 80],
        'medium' => ['width' => 1280, 'height' => 1280, 'quality' => 85],
        'large' => ['width' => 2048, 'height' => 2048, 'quality' => 90],
        'xlarge' => ['width' => 4096, 'height' => 4096, 'quality' => 92],
    ];

    protected $fillable = ['tenant_id', 'user_id', 'parent_id', 'type', 'scope', 'name', 'mime_type', 'size'];

    /**
     * Audit create/update/delete/restore on FileItems so Customer Admins can
     * see "who uploaded this folder" or "who renamed that file". We log a
     * compact attribute set — the full media blob doesn't belong in an audit
     * trail, and a bare timestamp change (Eloquent's updated_at touch after
     * a related media insert) shouldn't produce a noisy row either.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'scope', 'type', 'parent_id', 'size', 'mime_type'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('files');
    }

    /**
     * Pin to the central connection so queries don't follow the tenant schema
     * switch performed by stancl/tenancy middleware — file_items lives in the
     * central DB alongside users and tenants, not inside per-tenant schemas.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    /**
     * The UUID lives in its own column — id is still the primary key.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getIncrementing(): bool
    {
        return true;
    }

    public function getKeyType(): string
    {
        return 'int';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<FileItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isFolder(): bool
    {
        return $this->type === self::TYPE_FOLDER;
    }

    public function isCompanyScope(): bool
    {
        return $this->scope === self::SCOPE_COMPANY;
    }

    public function isPersonalScope(): bool
    {
        return $this->scope === self::SCOPE_PERSONAL;
    }

    /**
     * @return HasOne<CompanyFileLink, $this>
     */
    public function companyLink(): HasOne
    {
        return $this->hasOne(CompanyFileLink::class);
    }

    public function isImage(): bool
    {
        return $this->mime_type !== null && str_starts_with($this->mime_type, 'image/');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
        $this->addMediaCollection('doc_preview')->singleFile();
        $this->addMediaCollection('video_preview')->singleFile();
        $this->addMediaCollection('video_web')->singleFile();
    }

    public function isVideo(): bool
    {
        return $this->mime_type !== null && str_starts_with($this->mime_type, 'video/');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Folders have no media; skip. Only rasterize real image types — SVG
        // and non-image uploads have no meaningful raster preview.
        if ($media === null || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        if ($media->mime_type === 'image/svg+xml') {
            return;
        }

        foreach (self::IMAGE_SIZES as $name => $cfg) {
            $this->addMediaConversion($name)
                ->fit(Fit::Contain, $cfg['width'], $cfg['height'])
                ->format('webp')
                ->quality($cfg['quality'])
                ->performOnCollections('file');
        }
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'parent_id' => 'integer',
        ];
    }

    /**
     * When a folder is soft-deleted, cascade the soft-delete to children too
     * — otherwise they'd remain visible in the parent's listing after the
     * parent disappeared. On force-delete the DB cascade handles it.
     */
    protected static function booted(): void
    {
        static::deleting(function (FileItem $item): void {
            if (! $item->isForceDeleting() && $item->isFolder()) {
                $item->children()->get()->each->delete();
            }
        });
    }
}
