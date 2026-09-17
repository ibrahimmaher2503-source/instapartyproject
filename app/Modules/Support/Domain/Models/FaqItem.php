<?php

declare(strict_types=1);

namespace App\Modules\Support\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $public_id
 * @property int $faq_category_id
 * @property array $question
 * @property array $answer
 * @property int $sort_order
 * @property bool $is_active
 */
class FaqItem extends Model
{
    use HasPublicId;
    use HasTranslations;
    use Searchable;

    public array $translatable = ['question', 'answer'];

    protected $fillable = [
        'public_id', 'faq_category_id', 'question', 'answer', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'question' => 'array',
            'answer' => 'array',
        ];
    }

    /** @return BelongsTo<FaqCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }

    /** @param Builder<FaqItem> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<FaqItem> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'question_en' => $this->getTranslation('question', 'en'),
            'question_ar' => $this->getTranslation('question', 'ar'),
            'answer_en' => $this->getTranslation('answer', 'en'),
            'answer_ar' => $this->getTranslation('answer', 'ar'),
            'faq_category_id' => $this->faq_category_id,
            'is_active' => $this->is_active,
        ];
    }
}
