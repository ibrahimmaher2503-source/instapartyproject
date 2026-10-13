<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class PublicMediaUrl
{
    public function publicPath(?string $path): ?string
    {
        return filled($path) && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }

    public function for(Media $media, string $conversion = ''): ?string
    {
        if ($conversion !== '' && $media->hasGeneratedConversion($conversion)
            && Storage::disk($media->conversions_disk ?: $media->disk)->exists($media->getPathRelativeToRoot($conversion))) {
            return $media->getUrl($conversion);
        }

        return Storage::disk($media->disk)->exists($media->getPathRelativeToRoot()) ? $media->getUrl() : null;
    }
}
