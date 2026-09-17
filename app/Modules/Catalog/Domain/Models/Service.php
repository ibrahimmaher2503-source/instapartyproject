<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceFactory;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\States\ServiceStatus\PendingReviewState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\ServiceState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicGallery;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Shared\Domain\Contracts\ChangeRequestSubject;
use App\Modules\Shared\Domain\Enums\ChangeRequestSubjectType;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelStates\HasStates;
use Spatie\Translatable\HasTranslations;

/**
 * @property ProductType $product_type
 * @property-read ServiceSaleDetail|null $saleDetail
 */
class Service extends Model implements ChangeRequestSubject, HasMedia
{
    use HasFactory;
    use HasPublicGallery;
    use HasPublicId;
    use HasStates;
    use HasTranslations;
    use InteractsWithMedia;
    use Searchable;
    use SoftDeletes;

    /**
     * Media collection registry consumed by HasPublicGallery + MediaCollectionConfig.
     * Per ADR-0047 §3 and spec 048-media-collections-phase1/data-model.md §3.
     *
     * @var array<string, array<string, mixed>>
     */
    public static array $mediaCollectionRegistry = [
        'gallery' => [
            'trait' => 'HasPublicGallery',
            'disk' => 's3-public',
            'max_files' => 11,
            'min_files' => 0,
            'min_files_on_publish' => 1,
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size_bytes' => 5 * 1024 * 1024,
            'conversions' => [
                'thumb' => ['width' => 200, 'format' => 'webp'],
                'medium' => ['width' => 800, 'format' => 'webp'],
                'large' => ['width' => 1600, 'format' => 'webp'],
            ],
            'reorderable' => true,
            'image_editor' => false,
            'audit_logged' => false,
            'order_version_column' => 'gallery_order_version',
        ],
    ];

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'category_id',
        'product_type',
        'name',
        'short_description',
        'long_description',
        'slug',
        'moderation_notes',
        'moderated_at',
        'moderated_by',
        'base_price_minor',
        'base_price_currency',
        'is_featured',
        'gallery_order_version',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'short_description', 'long_description', 'moderation_notes'];

    protected $casts = [
        'product_type' => ProductType::class,
        'status' => ServiceState::class,
        'moderated_at' => 'datetime',
        'is_featured' => 'boolean',
        'base_price' => MoneyCast::class.':base_price',
    ];

    public function registerMediaCollections(): void
    {
        $this->registerPublicGalleryCollections();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerPublicGalleryConversions($media);
    }

    public function getChangeRequestSubjectType(): ChangeRequestSubjectType
    {
        return ChangeRequestSubjectType::Service;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Cross-module reference — acceptable for FK resolution; never call this from Catalog Actions.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function rentalDetail(): HasOne
    {
        return $this->hasOne(ServiceRentalDetail::class);
    }

    public function saleDetail(): HasOne
    {
        return $this->hasOne(ServiceSaleDetail::class);
    }

    public function digitalDetail(): HasOne
    {
        return $this->hasOne(ServiceDigitalDetail::class);
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(ServiceInventoryReservation::class);
    }

    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceTheme::class,
            'service_themes_pivot',
            'service_id',
            'service_theme_id',
        )->withPivot('sort_order')->orderBy('sort_order');
    }

    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(ServiceAvailabilityBlock::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'subject_id')
            ->where('subject_type', 'service');
    }

    public function serviceChangeRequests(): HasMany
    {
        return $this->hasMany(ServiceChangeRequest::class);
    }

    public function hasOpenChangeRequest(): bool
    {
        return $this->serviceChangeRequests()
            ->whereIn('status', ['pending', 'awaiting_clarification'])
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereState('status', PublishedState::class);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->whereState('status', PendingReviewState::class);
    }

    public function scopeForType(Builder $query, ProductType $type): Builder
    {
        return $query->where('product_type', $type);
    }

    public function scopeForVendor(Builder $query, int $vendorProfileId): Builder
    {
        return $query->where('vendor_profile_id', $vendorProfileId);
    }

    // -------------------------------------------------------------------------
    // Scout / Meilisearch
    // -------------------------------------------------------------------------

    /**
     * Only published services should be indexed.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status instanceof PublishedState;
    }

    /**
     * Eager-load relationships when building the search index.
     *
     * @return array<int, string>
     */
    public function searchableWith(): array
    {
        return ['category.occasions', 'vendor.coverageAreas', 'rentalDetail', 'saleDetail', 'digitalDetail'];
    }

    /**
     * Build the Meilisearch document for this service.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name_en' => $this->getTranslation('name', 'en'),
            'name_ar' => $this->getTranslation('name', 'ar'),
            'short_description_en' => $this->getTranslation('short_description', 'en'),
            'short_description_ar' => $this->getTranslation('short_description', 'ar'),
            'product_type' => $this->product_type->value,
            'category_id' => $this->category_id,
            'vendor_id' => $this->vendor_profile_id,
            'occasion_ids' => $this->category?->occasions->pluck('id')->toArray() ?? [],
            // Cities the service can be delivered to: the vendor's primary city
            // plus every coverage area. Used by the Discovery city filter.
            'coverage_city_ids' => array_values(array_unique(array_filter(array_merge(
                [optional($this->vendor)->primary_city_id],
                optional($this->vendor)->coverageAreas?->pluck('city_id')->all() ?? [],
            )))),
            'price_minor' => $this->base_price_minor,
            'currency' => $this->base_price_currency,
            'status' => $this->status->getValue(),
            'is_active' => $this->status instanceof PublishedState,
            'rating_avg' => (float) ($this->rating_avg ?? 0),
            'vendor_rating' => (float) (optional($this->vendor)->rating_avg ?? 0.0),
            'requires_electricity' => optional($this->rentalDetail)->requires_electricity,
            'requires_outdoor_space' => optional($this->rentalDetail)->requires_outdoor_space,
            'is_perishable' => optional($this->saleDetail)->is_perishable,
            'allows_customization' => optional($this->saleDetail)->allows_customization,
            'delivery_method' => optional($this->digitalDetail)->delivery_method?->value,
            'has_expiry' => optional($this->digitalDetail)->has_expiry,
            'cover_image_url' => rescue(fn (): ?string => $this->getFirstMediaUrl('gallery', 'thumb') ?: null, null, false),
        ];
    }
}
