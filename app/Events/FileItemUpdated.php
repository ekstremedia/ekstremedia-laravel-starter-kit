<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\FileItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on the owner's private channel whenever a FileItem changes in a
 * way the UI needs to know about — preview conversions finishing, a rename,
 * or a delete. Carries a minimal payload so the Vue page can patch the item
 * in place without a full re-fetch.
 */
class FileItemUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FileItem $item) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->item->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'FileItemUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $media = $this->item->getFirstMedia('file');
        $doc = $this->item->getFirstMedia('doc_preview');
        $videoPoster = $this->item->getFirstMedia('video_preview');
        $videoWeb = $this->item->getFirstMedia('video_web');

        $isVideo = $this->item->isVideo();
        $webCompatible = $media ? $media->getCustomProperty('web_compatible', false) : false;
        $videoReady = $isVideo && ($videoWeb !== null || (bool) $webCompatible);
        $videoProcessing = $isVideo && ! $videoReady;

        $docPreviewMimes = config('files.preview_mime_types', []);
        $docPreviewProcessing = in_array((string) $this->item->mime_type, $docPreviewMimes, true) && $doc === null;
        $imageThumbProcessing = $this->item->isImage() && $media !== null && ! $media->hasGeneratedConversion('thumb');
        $previewProcessing = $videoProcessing || $docPreviewProcessing || $imageThumbProcessing;

        return [
            'id' => $this->item->id,
            'uuid' => $this->item->uuid,
            'type' => $this->item->type,
            'name' => $this->item->name,
            'mime_type' => $this->item->mime_type,
            'size' => (int) $this->item->size,
            'parent_id' => $this->item->parent_id,
            'is_image' => $this->item->isImage(),
            'is_video' => $isVideo,
            'video_processing' => $videoProcessing,
            'video_ready' => $videoReady,
            'preview_processing' => $previewProcessing,
            'has_doc_preview' => $doc !== null,
            'thumbnail_url' => $media && $media->hasGeneratedConversion('thumb')
                ? $media->getUrl('thumb')
                : ($videoPoster?->getUrl() ?? $doc?->getUrl() ?? $media?->getUrl()),
            'preview_url' => $media && $media->hasGeneratedConversion('medium')
                ? $media->getUrl('medium')
                : ($videoPoster?->getUrl() ?? $doc?->getUrl() ?? $media?->getUrl()),
            'original_url' => $media?->getUrl(),
            'video_web_url' => $videoWeb ? $videoWeb->getUrl() : ($webCompatible ? $media->getUrl() : null),
            'video_poster_url' => $videoPoster?->getUrl(),
        ];
    }
}
