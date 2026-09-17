<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class HomepageSettings extends Model implements HasMedia
{
    use HasPublicId;
    use InteractsWithMedia;

    protected $table = 'homepage_settings';

    protected $fillable = [
        'public_id',
        'singleton',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [];
    }

    /**
     * Return (or create) the single HomepageSettings row.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['singleton' => 1],
            [],
        );
    }

    public function registerMediaCollections(): void
    {
        $disk = config('filesystems.media_disk_public', 's3-public');
        $this->addMediaCollection('hero')->singleFile()->useDisk($disk);
    }
}
