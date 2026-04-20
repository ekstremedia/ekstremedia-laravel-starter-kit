<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\FileItem;
use App\Models\FileShare;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Manages share-links created by the owner of a FileItem.
 *
 * Public (unauthenticated) viewing of those links lives in PublicShareController.
 */
class FileShareController extends Controller
{
    public function store(Request $request, FileItem $file): JsonResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertOwns($file, $user->id, $tenant->id);
        $this->assertFeatureAvailable($tenant, $user);
        abort_unless($user->can('share files'), 403, __('files.permission_denied'));

        $maxDays = AppSetting::current()->max_share_days ?? 7;

        $data = $request->validate([
            'expires_in_hours' => 'nullable|integer|min:1|max:'.($maxDays * 24),
            'password' => 'nullable|string|min:4|max:128',
        ]);

        $hours = (int) ($data['expires_in_hours'] ?? ($maxDays * 24));

        $share = FileShare::create([
            'token' => Str::random(32),
            'file_item_id' => $file->id,
            'created_by' => $user->id,
            'expires_at' => now()->addHours($hours),
            'password_hash' => ! empty($data['password']) ? Hash::make($data['password']) : null,
        ]);

        return response()->json([
            'share' => $this->formatShare($share),
            'url' => route('public.share.view', $share->token),
        ]);
    }

    public function index(Request $request, FileItem $file): JsonResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertOwns($file, $user->id, $tenant->id);

        $shares = FileShare::where('file_item_id', $file->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => $this->formatShare($s));

        return response()->json(['shares' => $shares]);
    }

    public function destroy(Request $request, FileShare $share): RedirectResponse
    {
        // The file_shares → file_items FK cascades on delete, so a share
        // without its FileItem shouldn't exist in the DB; ownership check
        // is still required.
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertOwns($share->fileItem, $user->id, $tenant->id);
        abort_unless($user->can('share files'), 403, __('files.permission_denied'));

        $share->delete();

        return back()->with('success', __('files.share_revoked'));
    }

    /**
     * Generate a one-click signed download URL — no password, expiry comes
     * from Laravel's URL signer. Good for quick file-only sharing.
     */
    public function quickSignedLink(Request $request, FileItem $file): JsonResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();
        $this->assertOwns($file, $user->id, $tenant->id);
        $this->assertFeatureAvailable($tenant, $user);
        abort_unless($user->can('share files'), 403, __('files.permission_denied'));

        if ($file->isFolder()) {
            abort(422, __('files.cannot_share_folder_quick'));
        }

        $maxDays = AppSetting::current()->max_share_days ?? 7;
        $hours = (int) $request->integer('hours', 24);
        $hours = max(1, min($hours, $maxDays * 24));

        $url = URL::temporarySignedRoute(
            'public.share.signed',
            now()->addHours($hours),
            ['file' => $file->id],
        );

        return response()->json([
            'url' => $url,
            'expires_at' => now()->addHours($hours)->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatShare(FileShare $share): array
    {
        return [
            'id' => $share->id,
            'token' => $share->token,
            'url' => route('public.share.view', $share->token),
            'expires_at' => $share->expires_at->toIso8601String(),
            'has_password' => $share->requiresPassword(),
            'view_count' => $share->view_count,
            'last_viewed_at' => $share->last_viewed_at?->toIso8601String(),
        ];
    }

    private function currentTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('customer');
        if ($tenant instanceof Tenant) {
            return $tenant;
        }
        $slug = config('tenancy.default_customer_slug');
        $fallback = $slug ? Tenant::query()->where('slug', $slug)->first() : null;
        abort_if(! $fallback, 404);

        return $fallback;
    }

    private function assertOwns(FileItem $item, int $userId, int $tenantId): void
    {
        if ($item->user_id !== $userId || $item->tenant_id !== $tenantId) {
            throw new AccessDeniedHttpException;
        }
    }

    private function assertFeatureAvailable(Tenant $tenant, User $user): void
    {
        if (! AppSetting::current()->files_feature_enabled) {
            abort(404);
        }
        if (! $tenant->files_feature_enabled) {
            abort(404);
        }
        if (! ($user->settings()->resolved()['files_enabled'] ?? false)) {
            abort(403);
        }
    }
}
