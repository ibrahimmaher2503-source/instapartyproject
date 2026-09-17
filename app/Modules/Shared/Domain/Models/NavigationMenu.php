<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property NavigationSlot $slot
 * @property string $name
 * @property-read Collection<int, NavigationMenuItem> $items
 */
class NavigationMenu extends Model
{
    use HasPublicId;

    protected $table = 'navigation_menus';

    protected $fillable = ['public_id', 'slot', 'name'];

    protected function casts(): array
    {
        return ['slot' => NavigationSlot::class];
    }

    /** @return HasMany<NavigationMenuItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(NavigationMenuItem::class, 'menu_id');
    }

    /** @return HasMany<NavigationMenuItem, $this> */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id')->orderBy('position');
    }
}
