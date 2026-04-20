<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FileItem;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ApproachingStorageLimitNotification;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Two-tier storage accounting:
 *
 *   - **Billable** — the user's own uploads (FileItem `file` collection).
 *     This is what the user sees on /files and what quota enforcement uses.
 *     Scoped per-(user, tenant) so each customer the user belongs to has
 *     its own bucket.
 *
 *   - **System total** — every row in the `media` table, regardless of owner
 *     or collection. This is what the admin dashboard sums for capacity
 *     planning. Previews (doc_preview, video_preview, video_web), chat
 *     attachments, and user avatars live here only — never in billable.
 *
 * Image conversions (thumb/medium/large/xlarge) are stored as files on disk
 * under `{media.id}/conversions/` without their own DB row, so they're
 * naturally excluded from every query here.
 */
class StorageUsageService
{
    /** User-uploaded originals only — the collection that counts toward the user's quota. */
    private const BILLABLE_COLLECTION = 'file';

    /**
     * Billable bytes across every tenant the user belongs to. Used by the
     * denormalized `users.storage_used_bytes` column so the admin user list
     * sorting reflects "what the user is paying for, overall".
     */
    public function usedBytesForUser(User $user): int
    {
        return (int) $this->billableQuery($user, null)->sum('media.size');
    }

    /**
     * Billable bytes for (user, tenant). Powers the /files usage bar,
     * remaining-bytes check, and per-tenant threshold alerting.
     */
    public function usedBytesForUserInTenant(User $user, Tenant $tenant): int
    {
        return (int) $this->billableQuery($user, $tenant)->sum('media.size');
    }

    /**
     * Coarse-grained mime breakdown of the user's billable storage.
     *
     * @return array<string, int>
     */
    public function breakdownByTypeForUser(User $user): array
    {
        $rows = $this->billableQuery($user, null)
            ->selectRaw('media.mime_type, SUM(media.size) as total')
            ->groupBy('media.mime_type')
            ->get();

        $out = ['image' => 0, 'video' => 0, 'pdf' => 0, 'document' => 0, 'other' => 0];
        foreach ($rows as $row) {
            $out[$this->categorize((string) $row->mime_type)] += (int) $row->total;
        }

        return $out;
    }

    /**
     * @return array<int, array{user_id: int, name: string, email: string, bytes: int}>
     */
    public function topUsers(int $limit = 20): array
    {
        return User::query()
            ->orderByDesc('storage_used_bytes')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'email', 'storage_used_bytes'])
            ->map(fn (User $u) => [
                'user_id' => (int) $u->id,
                'name' => $u->fullName(),
                'email' => $u->email,
                'bytes' => (int) $u->storage_used_bytes,
            ])
            ->all();
    }

    /** System-wide total bytes (includes previews, chat, avatars). */
    public function systemTotalBytes(): int
    {
        return (int) Media::on($this->central())->sum('size');
    }

    /**
     * @return array<string, int>
     */
    public function systemBreakdownByType(): array
    {
        $rows = DB::connection($this->central())
            ->table('media')
            ->selectRaw('mime_type, SUM(size) as total')
            ->groupBy('mime_type')
            ->get();

        $out = ['image' => 0, 'video' => 0, 'pdf' => 0, 'document' => 0, 'other' => 0];
        foreach ($rows as $row) {
            $out[$this->categorize((string) $row->mime_type)] += (int) $row->total;
        }

        return $out;
    }

    /**
     * Admin dashboard pie — what share of system storage is user-billable vs
     * platform overhead (previews, chat attachments, avatars). Helps admins
     * decide whether preview/transcode strategies need tuning.
     *
     * @return array<string, int> bucket => bytes
     */
    public function systemBreakdownByCollection(): array
    {
        $rows = DB::connection($this->central())
            ->table('media')
            ->selectRaw('model_type, collection_name, SUM(size) as total')
            ->groupBy('model_type', 'collection_name')
            ->get();

        $out = [
            'billable' => 0,
            'doc_preview' => 0,
            'video_preview' => 0,
            'video_web' => 0,
            'chat' => 0,
            'avatar' => 0,
            'other' => 0,
        ];

        foreach ($rows as $row) {
            $bytes = (int) $row->total;
            $bucket = $this->bucketFor((string) $row->model_type, (string) $row->collection_name);
            $out[$bucket] += $bytes;
        }

        return $out;
    }

    public function recomputeForUser(User $user): int
    {
        $bytes = $this->usedBytesForUser($user);

        if ((int) $user->storage_used_bytes !== $bytes) {
            $user->forceFill(['storage_used_bytes' => $bytes])->saveQuietly();
        }

        return $bytes;
    }

    /**
     * Remaining bytes in this tenant under the user's quota. `null` when
     * quota is unlimited, `0` when quota is disabled or cap reached.
     */
    public function remainingBytesInTenant(User $user, Tenant $tenant): ?int
    {
        $quota = $user->settings()->resolved()['storage_quota_bytes'] ?? null;

        if ($quota === null) {
            return null;
        }

        $used = $this->usedBytesForUserInTenant($user, $tenant);

        return max(0, (int) $quota - $used);
    }

    /** 0-100(+) percent of quota consumed by this tenant's files. */
    public function percentUsedInTenant(User $user, Tenant $tenant): float
    {
        $quota = $user->settings()->resolved()['storage_quota_bytes'] ?? null;

        if ($quota === null || $quota <= 0) {
            return 0.0;
        }

        return round(($this->usedBytesForUserInTenant($user, $tenant) / $quota) * 100, 2);
    }

    /**
     * Fire threshold alerts for *this tenant only*. Each tenant has its own
     * slot in `storage_last_alerted_threshold` (keyed by tenant id), so a
     * user crossing 80% in Company A doesn't suppress an 80% alert for
     * Company B later.
     */
    public function checkAndNotifyThresholds(User $user, Tenant $tenant): void
    {
        $settings = $user->settings();
        $resolved = $settings->resolved();
        $quota = $resolved['storage_quota_bytes'] ?? null;

        if ($quota === null || $quota <= 0) {
            return;
        }

        $used = $this->usedBytesForUserInTenant($user, $tenant);
        $percent = ($used / $quota) * 100;

        $thresholdMap = is_array($resolved['storage_last_alerted_threshold'] ?? null)
            ? $resolved['storage_last_alerted_threshold']
            : [];
        $key = (string) $tenant->id;
        $last = $thresholdMap[$key] ?? null;

        $current = match (true) {
            $percent >= 100 => 100,
            $percent >= 95 => 95,
            $percent >= 80 => 80,
            default => null,
        };

        if ($current === null) {
            if (array_key_exists($key, $thresholdMap)) {
                unset($thresholdMap[$key]);
                $settings->merge(['storage_last_alerted_threshold' => $thresholdMap]);
            }

            return;
        }

        if ($current !== $last) {
            $user->notify(new ApproachingStorageLimitNotification(
                thresholdPercent: $current,
                usedBytes: $used,
                quotaBytes: (int) $quota,
                tenantId: $tenant->id,
                tenantName: $tenant->name,
            ));
            $thresholdMap[$key] = $current;
            $settings->merge(['storage_last_alerted_threshold' => $thresholdMap]);
        }
    }

    private function central(): string
    {
        return (string) config('tenancy.database.central_connection');
    }

    /**
     * Build the billable subquery: only media rows from the FileItem `file`
     * collection, optionally scoped to one tenant. Previews + chat + avatars
     * are intentionally excluded.
     *
     * Callers pick their own projection — leaving the SELECT empty here
     * avoids poisoning aggregate callers like `breakdownByTypeForUser`
     * (Postgres rejects non-grouped columns; SQLite silently accepts them).
     */
    private function billableQuery(User $user, ?Tenant $tenant): Builder
    {
        $conn = $this->central();

        return DB::connection($conn)
            ->table('media')
            ->join('file_items', function ($join): void {
                $join->on('file_items.id', '=', 'media.model_id')
                    ->where('media.model_type', FileItem::class);
            })
            ->where('media.collection_name', self::BILLABLE_COLLECTION)
            ->where('file_items.user_id', $user->id)
            ->when($tenant !== null, fn ($q) => $q->where('file_items.tenant_id', $tenant->id));
    }

    private function bucketFor(string $modelType, string $collection): string
    {
        if ($modelType === FileItem::class) {
            return match ($collection) {
                'file' => 'billable',
                'doc_preview' => 'doc_preview',
                'video_preview' => 'video_preview',
                'video_web' => 'video_web',
                default => 'other',
            };
        }

        if ($modelType === Message::class) {
            return 'chat';
        }

        if ($modelType === User::class) {
            return 'avatar';
        }

        return 'other';
    }

    private function categorize(string $mime): string
    {
        if ($mime === '') {
            return 'other';
        }
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }
        if (str_contains($mime, 'word') || str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')
            || str_contains($mime, 'presentation') || str_contains($mime, 'opendocument')
            || str_starts_with($mime, 'text/')) {
            return 'document';
        }

        return 'other';
    }
}
