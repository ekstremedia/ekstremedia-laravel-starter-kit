<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FileItemResource;
use App\Models\FileItem;
use App\Models\FileShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unauthenticated access to shared files and folders.
 *
 * Two flavours:
 *   - `/share/{token}` → full share (owner-created, tokenized, optional pass).
 *   - `/share/signed/file/{file}` → single-file quick link (Laravel-signed).
 */
class PublicShareController extends Controller
{
    public function view(Request $request, string $token): Response|RedirectResponse
    {
        $share = FileShare::where('token', $token)->firstOrFail();

        if ($share->isExpired()) {
            abort(410, __('share.expired'));
        }

        if ($share->requiresPassword() && ! $this->isUnlocked($request, $share)) {
            return Inertia::render('Share/Password', [
                'token' => $share->token,
                'action' => route('public.share.unlock', $share->token),
            ]);
        }

        $share->increment('view_count');
        $share->update(['last_viewed_at' => now()]);

        /** @var FileItem $item */
        $item = $share->fileItem()->with('media')->firstOrFail();

        return Inertia::render('Share/Show', [
            'item' => (new FileItemResource($item))->toArray($request),
            'children' => $item->isFolder()
                ? FileItem::where('parent_id', $item->id)
                    ->with('media')
                    ->orderByRaw("case when type = 'folder' then 0 else 1 end")
                    ->orderBy('name')
                    ->get()
                    ->map(fn (FileItem $i) => (new FileItemResource($i))->toArray($request))
                    ->all()
                : [],
            'share' => [
                'token' => $share->token,
                // Column is NOT NULL; the ?-> is defensive in case a future
                // migration relaxes that.
                'expires_at' => $share->expires_at->toIso8601String(),
            ],
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $share = FileShare::where('token', $token)->firstOrFail();

        $request->validate(['password' => 'required|string']);

        if (! $share->password_hash || ! Hash::check((string) $request->input('password'), $share->password_hash)) {
            return back()->withErrors(['password' => __('share.wrong_password')]);
        }

        session()->put("share.unlocked.{$token}", true);

        return redirect()->route('public.share.view', $token);
    }

    public function download(Request $request, string $token, int $fileId): BinaryFileResponse
    {
        $share = FileShare::where('token', $token)->firstOrFail();

        if ($share->isExpired()) {
            abort(410);
        }
        if ($share->requiresPassword() && ! $this->isUnlocked($request, $share)) {
            abort(403);
        }

        $root = $share->fileItem;
        /** @var FileItem $target */
        $target = FileItem::findOrFail($fileId);
        if ($target->id !== $root->id && ! $this->isDescendantOf($target, $root)) {
            abort(403);
        }

        $media = $target->getFirstMedia('file');
        abort_if(! $media, 404);

        return response()->download($media->getPath(), $target->name);
    }

    public function signedDownload(Request $request, int $file): BinaryFileResponse
    {
        // Defense in depth — the route already carries the `signed` middleware,
        // but a direct call here without the signature check would also bypass
        // expiry, so we assert it explicitly.
        abort_unless($request->hasValidSignature(), 403);

        /** @var FileItem $item */
        $item = FileItem::findOrFail($file);
        abort_if($item->isFolder(), 404);

        $media = $item->getFirstMedia('file');
        abort_if(! $media, 404);

        return response()->download($media->getPath(), $item->name);
    }

    private function isUnlocked(Request $request, FileShare $share): bool
    {
        return (bool) session("share.unlocked.{$share->token}");
    }

    private function isDescendantOf(FileItem $candidate, FileItem $ancestor): bool
    {
        $cursor = $candidate;
        while ($cursor->parent_id !== null) {
            if ($cursor->parent_id === $ancestor->id) {
                return true;
            }
            $cursor = FileItem::find($cursor->parent_id);
            if (! $cursor) {
                return false;
            }
        }

        return false;
    }
}
