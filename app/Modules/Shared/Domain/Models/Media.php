<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $media): void {
            if (empty($media->public_id)) {
                $media->public_id = (string) Str::ulid();
            }
        });
    }
}
