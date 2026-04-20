<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FileItemResource;
use App\Models\AppSetting;
use App\Models\FileItem;
use App\Models\Tenant;
use App\Services\StorageUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FileTrashController extends Controller
{
    public function __construct(private readonly StorageUsageService $usage) {}

    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);

        $items = FileItem::onlyTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->with('media')
            ->orderByDesc('deleted_at')
            ->get();

        return Inertia::render('Files/Trash', [
            'items' => FileItemResource::collection($items),
            'retention_days' => 30,
        ]);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);

        $item = FileItem::onlyTrashed()->findOrFail($id);
        $this->authorizeOwn($item, $user->id, $tenant->id);

        // If the parent is also trashed (or gone), restore to root — don't
        // resurrect a row whose parent_id points at nothing.
        if ($item->parent_id !== null) {
            $parent = FileItem::withTrashed()->find($item->parent_id);
            if (! $parent || $parent->trashed()) {
                $item->parent_id = null;
            }
        }

        $deletedAt = $item->deleted_at ? Carbon::instance($item->deleted_at) : null;
        $item->restore();

        // Cascade-restore descendants that were soft-deleted as part of the
        // same trash action (same deleted_at timestamp, sub-second window).
        if ($deletedAt && $item->isFolder()) {
            $this->cascadeRestore($item, $deletedAt);
        }

        $this->usage->recomputeForUser($user);

        return back()->with('success', __('files.restored'));
    }

    private function cascadeRestore(FileItem $folder, Carbon $deletedAt): void
    {
        $descendants = FileItem::onlyTrashed()
            ->where('parent_id', $folder->id)
            ->whereBetween('deleted_at', [$deletedAt->copy()->subSecond(), $deletedAt->copy()->addSecond()])
            ->get();

        foreach ($descendants as $d) {
            $d->restore();
            if ($d->isFolder()) {
                $this->cascadeRestore($d, $deletedAt);
            }
        }
    }

    public function forceDelete(Request $request, int $id): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);

        $item = FileItem::onlyTrashed()->findOrFail($id);
        $this->authorizeOwn($item, $user->id, $tenant->id);

        if ($item->isFolder()) {
            $this->cascadeForceDelete($item);
        }
        $item->forceDelete();
        $this->usage->recomputeForUser($user);

        return back()->with('success', __('files.force_deleted'));
    }

    public function empty(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);

        // Chunk so huge trashes don't load everything into memory.
        FileItem::onlyTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    $item->forceDelete();
                }
            });

        $this->usage->recomputeForUser($user);

        return back()->with('success', __('files.trash_emptied'));
    }

    /**
     * Force-delete every trashed descendant of a folder before the folder
     * itself — the DB cascade only fires on hard delete, and we want to
     * reach already-soft-deleted children too.
     */
    private function cascadeForceDelete(FileItem $folder): void
    {
        FileItem::onlyTrashed()
            ->where('parent_id', $folder->id)
            ->chunkById(100, function ($children): void {
                foreach ($children as $child) {
                    if ($child->isFolder()) {
                        $this->cascadeForceDelete($child);
                    }
                    $child->forceDelete();
                }
            });
    }

    private function currentTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('customer');
        if ($tenant instanceof Tenant) {
            return $tenant;
        }
        $slug = config('tenancy.default_customer_slug');
        $fallback = $slug ? Tenant::query()->where('slug', $slug)->first() : null;
        if (! $fallback) {
            abort(404);
        }

        return $fallback;
    }

    private function assertFeatureAvailable(Request $request, Tenant $tenant): void
    {
        if (! AppSetting::current()->files_feature_enabled) {
            abort(404);
        }

        $user = $request->user();
        $settings = $user->settings()->resolved();

        if (! $tenant->files_feature_enabled) {
            abort(404);
        }

        if (! ($settings['files_enabled'] ?? false)) {
            abort(403, __('files.not_enabled'));
        }
    }

    private function authorizeOwn(FileItem $item, int $userId, int $tenantId): void
    {
        if ($item->user_id !== $userId || $item->tenant_id !== $tenantId) {
            throw new AccessDeniedHttpException;
        }
    }
}
