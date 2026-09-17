<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Shared\Domain\Enums\NavigationTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $public_id
 * @property NavigationTargetType $target_type
 * @property string $target_value
 * @property string|null $icon
 * @property bool $opens_in_new_tab
 */
class NavigationMenuItem extends Model
{
    use HasPublicId;
    use HasTranslations;

    protected $table = 'navigation_menu_items';

    /** @var list<string> */
    public array $translatable = ['label'];

    protected $fillable = [
        'public_id', 'menu_id', 'parent_id', 'label',
        'target_type', 'target_value', 'icon',
        'is_visible', 'opens_in_new_tab', 'position',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => NavigationTargetType::class,
            'is_visible' => 'boolean',
            'opens_in_new_tab' => 'boolean',
        ];
    }

    /** @return BelongsTo<NavigationMenu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class, 'menu_id');
    }

    /** @return BelongsTo<NavigationMenuItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavigationMenuItem::class, 'parent_id');
    }

    /** @return HasMany<NavigationMenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(NavigationMenuItem::class, 'parent_id')->orderBy('position');
    }
}
