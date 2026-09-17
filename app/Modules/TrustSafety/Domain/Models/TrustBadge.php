<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $public_id
 * @property array $name
 * @property array $description
 * @property int $level
 * @property bool $is_active
 */
class TrustBadge extends Model implements HasMedia
{
    use HasPublicId;
    use HasTranslations;
    use InteractsWithMedia;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'public_id', 'name', 'description', 'level', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
            'name' => 'array',
            'description' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('trust-badge-icon')
            ->singleFile()
            ->useDisk('s3-public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(64)
            ->height(64)
            ->format('webp')
            ->nonQueued();
    }

    /** @return HasMany<VendorBadgeAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(VendorBadgeAssignment::class);
    }

    /** @param Builder<TrustBadge> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
