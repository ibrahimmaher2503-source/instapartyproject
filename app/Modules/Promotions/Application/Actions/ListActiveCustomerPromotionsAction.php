<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Actions;

use App\Modules\Promotions\Domain\Enums\PromoCodeScope;
use App\Modules\Promotions\Domain\Models\PromoCode;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

/**
 * Feature 054 — US11 (C4).
 *
 * Lists currently-active, customer-visible promotions. The locked `promo_codes`
 * schema (ADR-0046) carries no title/description columns and stores scope targets
 * as numeric `scope_id`; this Action resolves each target to its ULID `public_id`
 * (cross-module reads go through the query builder, never another module's model)
 * and stamps it onto the row as `scope_public_id` for the Resource to expose.
 */
class ListActiveCustomerPromotionsAction
{
    /**
     * @param  'global'|'category'|'vendor'|'service'|null  $scope  Frontend-facing scope ('global' === platform).
     * @return CursorPaginator<int, PromoCode>
     */
    public function execute(
        ?string $scope = null,
        ?string $vendorPublicId = null,
        int $limit = 20,
        ?string $cursor = null,
    ): CursorPaginator {
        $limit = max(1, min($limit, 50));

        $query = PromoCode::query()->active();

        if ($scope !== null) {
            $query->where('scope', $this->toDomainScope($scope)->value);
        }

        if ($vendorPublicId !== null) {
            $vendorId = DB::table('vendor_profiles')
                ->where('public_id', $vendorPublicId)
                ->value('id');

            // Unknown vendor → an impossible predicate yields an empty page.
            $query->where('scope', PromoCodeScope::Vendor->value)
                ->where('scope_id', $vendorId ?? 0);
        }

        $page = $query->orderByDesc('id')->cursorPaginate($limit, ['*'], 'cursor', $cursor);

        $this->attachScopePublicIds($page->items());

        return $page;
    }

    private function toDomainScope(string $scope): PromoCodeScope
    {
        return match ($scope) {
            'global' => PromoCodeScope::Platform,
            'category' => PromoCodeScope::Category,
            'vendor' => PromoCodeScope::Vendor,
            'service' => PromoCodeScope::Service,
            default => PromoCodeScope::Platform,
        };
    }

    /**
     * Batch-resolve numeric scope targets to ULIDs to avoid N+1 lookups.
     *
     * @param  array<int, PromoCode>  $promoCodes
     */
    private function attachScopePublicIds(array $promoCodes): void
    {
        $idsByTable = [
            'vendor_profiles' => [],
            'categories' => [],
            'services' => [],
        ];

        foreach ($promoCodes as $promo) {
            $table = $this->scopeTable($promo->scope);
            if ($table !== null && $promo->scope_id !== null) {
                $idsByTable[$table][] = $promo->scope_id;
            }
        }

        $maps = [];
        foreach ($idsByTable as $table => $ids) {
            $maps[$table] = $ids === []
                ? []
                : DB::table($table)->whereIn('id', array_unique($ids))->pluck('public_id', 'id')->all();
        }

        foreach ($promoCodes as $promo) {
            $table = $this->scopeTable($promo->scope);
            $promo->setAttribute(
                'scope_public_id',
                $table !== null && $promo->scope_id !== null
                    ? ($maps[$table][$promo->scope_id] ?? null)
                    : null,
            );
        }
    }

    private function scopeTable(PromoCodeScope $scope): ?string
    {
        return match ($scope) {
            PromoCodeScope::Vendor => 'vendor_profiles',
            PromoCodeScope::Category => 'categories',
            PromoCodeScope::Service => 'services',
            PromoCodeScope::Platform => null,
        };
    }
}
