<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Shared\Domain\Concerns\HasSingleImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $public_id
 * @property string $slug
 * @property int|null $occasion_id
 * @property int|null $min_budget_minor
 * @property int|null $max_budget_minor
 * @property string|null $budget_currency
 * @property bool $is_published
 * @property int $display_order
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class PackageRecommendation extends Model implements HasMedia
{
    use HasPublicId;
    use HasSingleImage;
    use HasTranslations;
    use InteractsWithMedia;

    /** @var array<string, array<string, mixed>> */
    public static array $mediaCollectionRegistry = [
        'hero' => [
            'trait' => 'HasSingleImage',
            'disk' => 's3-public',
            'max_files' => 1,
            'min_files' => 0,
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size_bytes' => 3 * 1024 * 1024,
            'conversions' => [
                'thumb' => ['width' => 400, 'format' => 'webp'],
                'medium' => ['width' => 800, 'height' => 600, 'format' => 'webp'],
                'large' => ['width' => 1600, 'height' => 900, 'format' => 'webp'],
            ],
            'reorderable' => false,
            'image_editor' => true,
            'audit_logged' => false,
            'order_version_column' => null,
        ],
    ];

    protected $fillable = [
        'public_id', 'slug', 'name', 'description', 'occasion_id',
        'min_budget_minor', 'max_budget_minor', 'budget_currency',
        'is_published', 'display_order', 'created_by', 'updated_by',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'min_budget_minor' => 'integer',
            'max_budget_minor' => 'integer',
            'is_published' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->registerSingleImageCollections();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerSingleImageConversions($media);
    }

    /** @param Builder<PackageRecommendation> $query
     * @return Builder<PackageRecommendation>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
