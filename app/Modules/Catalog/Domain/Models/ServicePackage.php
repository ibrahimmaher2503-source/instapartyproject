<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicGallery;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class ServicePackage extends Model implements HasMedia
{
    use HasFactory;
    use HasPublicGallery;
    use HasPublicId;
    use HasTranslations;
    use InteractsWithMedia;
    use SoftDeletes;

    /** @var array<string, array<string, mixed>> */
    public static array $mediaCollectionRegistry = [
        'gallery' => [
            'trait' => 'HasPublicGallery',
            'disk' => 's3-public',
            'max_files' => 6,
            'min_files' => 0,
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size_bytes' => 5 * 1024 * 1024,
            'conversions' => [
                'thumb' => ['width' => 200, 'format' => 'webp'],
                'medium' => ['width' => 800, 'format' => 'webp'],
            ],
            'reorderable' => true,
            'image_editor' => false,
            'audit_logged' => false,
        ],
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'public_id', 'service_id', 'code', 'name', 'description', 'price_minor', 'currency', 'is_active',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function components(): HasMany
    {
        return $this->hasMany(ServicePackageComponent::class)->orderBy('sort_order');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereHas('components')
            ->whereHas('service', fn (Builder $services): Builder => $services->displayable()
                ->whereNull('services.deleted_at')
                ->whereColumn('services.base_price_currency', 'service_packages.currency')
                ->whereHas('vendor', fn (Builder $vendors): Builder => $vendors->approved()
                    ->whereNull('vendor_profiles.deleted_at')
                    ->whereHas('approvedTypes', fn (Builder $types): Builder => $types
                        ->whereColumn('vendor_approved_product_types.product_type', 'services.product_type'))));
    }

    public function registerMediaCollections(): void
    {
        $this->registerPublicGalleryCollections();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerPublicGalleryConversions($media);
    }
}
