<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class BrandingSetting extends Model implements HasMedia
{
    use HasPublicId;
    use HasTranslations;
    use InteractsWithMedia;

    protected $table = 'branding_settings';

    public array $translatable = ['site_name', 'tagline', 'address_line'];

    protected $fillable = [
        'public_id',
        'singleton',
        'site_name',
        'tagline',
        'support_email',
        'support_phone',
        'whatsapp_number',
        'social',
        'address_line',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'social' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['singleton' => 1],
            [
                'site_name' => ['en' => 'InstaParty', 'ar' => 'InstaParty'],
                'tagline' => ['en' => '', 'ar' => ''],
                'address_line' => ['en' => '', 'ar' => ''],
                'social' => [],
            ],
        );
    }

    public function registerMediaCollections(): void
    {
        $disk = config('filesystems.media_disk_public', 's3-public');
        foreach (['logo_light', 'logo_dark', 'favicon', 'og_image', 'app_store_badge', 'play_store_badge'] as $collection) {
            $this->addMediaCollection($collection)->singleFile()->useDisk($disk);
        }
    }
}
