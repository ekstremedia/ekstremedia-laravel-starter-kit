<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\FileItemUpdated;
use App\Http\Resources\FileItemResource;
use App\Jobs\GenerateDocumentPreview;
use App\Jobs\GenerateVideoPreview;
use App\Models\AppSetting;
use App\Models\FileItem;
use App\Models\Tenant;
use App\Services\StorageUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FileItemController extends Controller
{
    public function __construct(private readonly StorageUsageService $usage) {}

    public function index(Request $request, ?FileItem $folder = null): Response
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);

        if ($folder !== null && $folder->exists) {
            $this->authorizeOwn($folder, $user->id, $tenant->id);
            if (! $folder->isFolder()) {
                abort(404);
            }
        }

        $parentId = $folder?->id;

        $query = FileItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('parent_id', $parentId)
            ->with('media');

        if ($search = $request->string('q')->toString()) {
            // Case-insensitive prefix search, escaping LIKE wildcards.
            $escaped = addcslashes($search, '%_\\');
            $query->where('name', 'ilike', "%{$escaped}%");
        }

        $items = $query->orderByRaw("case when type = 'folder' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        $usedBytes = $this->usage->usedBytesForUserInTenant($user, $tenant);

        return Inertia::render('Files/Index', [
            'items' => FileItemResource::collection($items),
            'breadcrumbs' => $this->breadcrumbs($folder),
            'current_folder' => $folder?->only(['id', 'name', 'uuid']),
            'usage' => [
                'used_bytes' => $usedBytes,
                'quota_bytes' => $user->settings()->resolved()['storage_quota_bytes'] ?? null,
                'percent' => $this->usage->percentUsedInTenant($user, $tenant),
            ],
            'search' => $search ?: null,
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => ['nullable', 'integer', $this->existsFileItemRule()],
        ]);

        if (isset($data['parent_id'])) {
            $parent = FileItem::findOrFail($data['parent_id']);
            $this->authorizeOwn($parent, $user->id, $tenant->id);
            if (! $parent->isFolder()) {
                abort(422, 'Parent must be a folder.');
            }
        }

        $folder = FileItem::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'parent_id' => $data['parent_id'] ?? null,
            'type' => FileItem::TYPE_FOLDER,
            'name' => $this->uniqueName($tenant->id, $user->id, $data['parent_id'] ?? null, $data['name']),
        ]);

        return back()->with('success', __('files.folder_created', ['name' => $folder->name]));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200',
            'parent_id' => ['nullable', 'integer', $this->existsFileItemRule()],
        ]);

        $parentId = $request->integer('parent_id') ?: null;
        if ($parentId !== null) {
            $parent = FileItem::findOrFail($parentId);
            $this->authorizeOwn($parent, $user->id, $tenant->id);
            if (! $parent->isFolder()) {
                abort(422, 'Parent must be a folder.');
            }
        }

        $created = 0;
        $previewTargets = [];
        $videoTargets = [];
        DB::transaction(function () use ($request, $tenant, $user, $parentId, &$created, &$previewTargets, &$videoTargets): void {
            foreach ($request->file('files', []) as $file) {
                $name = $this->uniqueName($tenant->id, $user->id, $parentId, $file->getClientOriginalName());
                // getSize() can return false on partial uploads — fall back to
                // 0 so we don't store an int-cast-of-false (also 0) silently.
                $size = $file->getSize();
                $item = FileItem::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'parent_id' => $parentId,
                    'type' => FileItem::TYPE_FILE,
                    'name' => $name,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $size === false ? 0 : (int) $size,
                ]);

                $item->addMedia($file)->toMediaCollection('file');
                $created++;

                // Office/PDF files get a first-page render via Gotenberg+Imagick
                // on the queue, then broadcast an update to the owner's channel.
                if (in_array((string) $item->mime_type, config('files.preview_mime_types', []), true)) {
                    $previewTargets[] = $item->id;
                }
                // Videos get a poster frame + web-friendly MP4 via ffmpeg on
                // the queue. UI shows a spinner until the broadcast arrives.
                if ($item->isVideo()) {
                    $videoTargets[] = $item->id;
                }
            }
        });

        // Dispatch preview jobs + broadcast initial "new item" events after
        // the transaction commits so the worker sees a row it can load.
        foreach ($previewTargets as $id) {
            GenerateDocumentPreview::dispatch($id);
        }
        foreach ($videoTargets as $id) {
            GenerateVideoPreview::dispatch($id);
        }
        foreach (array_unique(array_merge($previewTargets, $videoTargets)) as $id) {
            $fresh = FileItem::with('media')->find($id);
            if ($fresh) {
                event(new FileItemUpdated($fresh));
            }
        }

        // Refresh the denormalized column (billable across every tenant) and
        // fire threshold alerts for the tenant the user uploaded into.
        $this->usage->recomputeForUser($user);
        $this->usage->checkAndNotifyThresholds($user->fresh(), $tenant);

        return back()->with('success', __('files.upload_success', ['count' => $created]));
    }

    public function update(Request $request, FileItem $file): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);
        $this->authorizeOwn($file, $user->id, $tenant->id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parent_id' => ['sometimes', 'nullable', 'integer', $this->existsFileItemRule()],
        ]);

        if (array_key_exists('parent_id', $data)) {
            if ($data['parent_id'] !== null) {
                $parent = FileItem::findOrFail($data['parent_id']);
                $this->authorizeOwn($parent, $user->id, $tenant->id);
                if (! $parent->isFolder()) {
                    abort(422, 'Destination must be a folder.');
                }
                // Disallow moving a folder into its own descendant.
                if ($file->isFolder() && $this->isDescendantOf($parent, $file)) {
                    abort(422, 'Cannot move a folder into its own descendant.');
                }
            }
            $file->parent_id = $data['parent_id'];
        }

        if (array_key_exists('name', $data) && $data['name'] !== '') {
            $file->name = $this->uniqueName($tenant->id, $user->id, $file->parent_id, $data['name'], $file->id);
        }

        $file->save();

        return back()->with('success', __('files.updated'));
    }

    public function destroy(Request $request, FileItem $file): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);
        $this->authorizeOwn($file, $user->id, $tenant->id);

        $file->delete();
        $this->usage->recomputeForUser($user);

        return back()->with('success', __('files.deleted'));
    }

    public function download(Request $request, FileItem $file): BinaryFileResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertFeatureAvailable($request, $tenant);
        $this->authorizeOwn($file, $user->id, $tenant->id);

        if ($file->isFolder()) {
            abort(404);
        }

        $media = $file->getFirstMedia('file');
        if (! $media) {
            abort(404);
        }

        $requested = $request->string('size')->toString();
        if ($requested !== '' && $requested !== 'original' && $media->hasGeneratedConversion($requested)) {
            $path = $media->getPath($requested);
            // Derive the conversion's extension from the file on disk rather
            // than assuming .webp — conversions can be any format Spatie is
            // configured to produce.
            $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'webp';
            $filename = pathinfo($file->name, PATHINFO_FILENAME).'-'.$requested.'.'.$ext;
        } else {
            $path = $media->getPath();
            $filename = $file->name;
        }

        return response()->download($path, $filename);
    }

    private function currentTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('customer');

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        // Single-tenant installs (tenancy disabled) — fall back to the
        // default-customer row. When that's missing the feature simply 404s.
        $slug = config('tenancy.default_customer_slug');
        $fallback = $slug ? Tenant::query()->where('slug', $slug)->first() : null;

        if (! $fallback) {
            abort(404);
        }

        return $fallback;
    }

    /**
     * Validation rule that checks a file_items.id exists on the central
     * connection. The built-in `exists:` rule binds to the default
     * connection, which is swapped to the tenant schema by stancl/tenancy —
     * file_items lives in the central DB, so we look it up through the model.
     */
    private function existsFileItemRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            if (! FileItem::whereKey($value)->exists()) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }

    private function assertFeatureAvailable(Request $request, Tenant $tenant): void
    {
        // Global kill-switch — admin can disable the whole subsystem from
        // /admin/settings. Image previews (avatars, chat thumbnails) still
        // work because those live under User/Message, not FileItem.
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

        if (($settings['storage_quota_bytes'] ?? null) === 0) {
            abort(403, __('files.quota_disabled'));
        }
    }

    private function authorizeOwn(FileItem $item, int $userId, int $tenantId): void
    {
        if ($item->user_id !== $userId || $item->tenant_id !== $tenantId) {
            throw new AccessDeniedHttpException;
        }
    }

    private function isDescendantOf(FileItem $candidate, FileItem $possibleAncestor): bool
    {
        $cursor = $candidate;
        while ($cursor->parent_id !== null) {
            if ($cursor->parent_id === $possibleAncestor->id) {
                return true;
            }
            $cursor = FileItem::find($cursor->parent_id);
            if (! $cursor) {
                return false;
            }
        }

        return false;
    }

    /**
     * Suffix " (n)" until the name is unique within the folder. Excludes
     * $ignoreId so renaming to the same name is a no-op.
     */
    private function uniqueName(int $tenantId, int $userId, ?int $parentId, string $name, ?int $ignoreId = null): string
    {
        $base = $name;
        $i = 1;
        while (FileItem::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $name = $this->appendSuffix($base, ++$i);
        }

        return $name;
    }

    private function appendSuffix(string $name, int $n): string
    {
        $dot = strrpos($name, '.');
        if ($dot === false || $dot === 0) {
            return $name." ({$n})";
        }

        return substr($name, 0, $dot)." ({$n})".substr($name, $dot);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function breadcrumbs(?FileItem $folder): array
    {
        $trail = [];
        $cursor = $folder;
        while ($cursor) {
            array_unshift($trail, [
                'id' => $cursor->id,
                'name' => $cursor->name,
            ]);
            $cursor = $cursor->parent;
        }

        return $trail;
    }
}
