<?php

declare(strict_types=1);

namespace App\Modules\Support\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $public_id
 * @property array $name
 * @property string $slug
 * @property int $sort_order
 * @property bool $is_active
 */
class FaqCategory extends Model
{
    use HasPublicId;
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = [
        'public_id', 'name', 'slug', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'name' => 'array',
        ];
    }

    /** @return HasMany<FaqItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(FaqItem::class);
    }

    /** @param Builder<FaqCategory> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<FaqCategory> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order');
    }
}
