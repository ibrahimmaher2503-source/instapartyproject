<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $promo_code_id
 * @property int $user_id
 * @property int|null $booking_id
 * @property int $discount_minor
 * @property string $discount_currency
 * @property Carbon $used_at
 */
class PromoCodeUse extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promo_code_id', 'user_id', 'booking_id',
        'discount_minor', 'discount_currency', 'used_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PromoCode, $this> */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function delete(): ?bool
    {
        throw new LogicException('PromoCodeUse is append-only and cannot be deleted.');
    }
}
