<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Models;

use App\Modules\Promotions\Domain\Enums\PromoCodeScope;
use App\Modules\Promotions\Domain\Enums\PromoCodeType;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $public_id
 * @property string $code
 * @property PromoCodeType $type
 * @property int|null $discount_percent
 * @property int|null $discount_minor
 * @property string $discount_currency
 * @property int|null $min_order_minor
 * @property string $min_order_currency
 * @property int|null $max_discount_minor
 * @property string $max_discount_currency
 * @property int|null $max_uses
 * @property int $used_count
 * @property PromoCodeScope $scope
 * @property int|null $scope_id
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
class PromoCode extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id', 'code', 'type',
        'discount_percent', 'discount_minor', 'discount_currency',
        'min_order_minor', 'min_order_currency',
        'max_discount_minor', 'max_discount_currency',
        'max_uses', 'used_count',
        'scope', 'scope_id',
        'starts_at', 'expires_at', 'is_active',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromoCodeType::class,
            'scope' => PromoCodeScope::class,
            'discount_minor' => 'integer',
            'min_order_minor' => 'integer',
            'max_discount_minor' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'discount_percent' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return HasMany<PromoCodeUse, $this> */
    public function uses(): HasMany
    {
        return $this->hasMany(PromoCodeUse::class);
    }

    /** @param Builder<PromoCode> $query */
    public function scopeActive(Builder $query): void
    {
        $now = now();
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
    }

    /** @param Builder<PromoCode> $query */
    public function scopeForScope(Builder $query, PromoCodeScope $scope, ?int $scopeId): void
    {
        $query->where(fn ($q) => $q
            ->where('scope', PromoCodeScope::Platform->value)
            ->orWhere(fn ($inner) => $inner
                ->where('scope', $scope->value)
                ->where('scope_id', $scopeId)
            )
        );
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }
}
