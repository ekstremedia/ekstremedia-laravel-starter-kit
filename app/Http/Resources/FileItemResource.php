<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FileItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin FileItem
 */
class FileItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->isFolder() ? null : $this->getFirstMedia('file');
        $docPreview = $this->isFolder() ? null : $this->getFirstMedia('doc_preview');
        $videoPoster = $this->isFolder() ? null : $this->getFirstMedia('video_preview');
        $videoWeb = $this->isFolder() ? null : $this->getFirstMedia('video_web');

        $isVideo = $this->isVideo();
        // For videos we consider processing complete once we either have a
        // transcoded web MP4, or the source was already web-compatible
        // (flagged on the original media row by the job).
        $webCompatible = $media ? $media->getCustomProperty('web_compatible', false) : false;
        $videoReady = $isVideo && ($videoWeb !== null || (bool) $webCompatible);
        $videoProcessing = $isVideo && ! $videoReady;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->size,
            'parent_id' => $this->parent_id,
            'is_image' => $this->isImage(),
            'is_video' => $isVideo,
            'video_processing' => $videoProcessing,
            'video_ready' => $videoReady,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'thumbnail_url' => $media && $media->hasGeneratedConversion('thumb')
                ? $media->getUrl('thumb')
                : ($this->isImage() && $media
                    ? $media->getUrl()
                    : ($videoPoster?->getUrl() ?? $docPreview?->getUrl())),
            'preview_url' => $media && $media->hasGeneratedConversion('medium')
                ? $media->getUrl('medium')
                : ($videoPoster?->getUrl() ?? $docPreview?->getUrl() ?? $media?->getUrl()),
            'original_url' => $media?->getUrl(),
            'video_web_url' => $videoWeb ? $videoWeb->getUrl() : ($webCompatible ? $media->getUrl() : null),
            'video_poster_url' => $videoPoster?->getUrl(),
            'available_sizes' => $media ? $this->availableSizes($media) : null,
            'has_doc_preview' => $docPreview !== null,
        ];
    }

    /**
     * @return array<string, array{url: string, width: int, height: int}>
     */
    private function availableSizes(Media $media): array
    {
        $sizes = [];
        foreach (FileItem::IMAGE_SIZES as $name => $cfg) {
            if ($media->hasGeneratedConversion($name)) {
                $sizes[$name] = [
                    'url' => $media->getUrl($name),
                    'width' => $cfg['width'],
                    'height' => $cfg['height'],
                ];
            }
        }

        return $sizes;
    }
}
