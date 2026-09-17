<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use Illuminate\Support\Facades\DB;

final class ListCustomerLoyaltyBalancesAction
{
    public function __construct(
        private readonly LoyaltyProgramRepository $programs,
        private readonly BalanceCalculator $balance,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function execute(int $userId): array
    {
        // Find all distinct vendor_profile_ids this customer has ledger entries for
        $vendorProfileIds = LoyaltyLedgerEntry::query()
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('vendor_profile_id')
            ->toArray();

        if (empty($vendorProfileIds)) {
            return [];
        }

        // Eager-load vendor profiles (public_id + business_name) in one query
        $vendors = DB::table('vendor_profiles')
            ->whereIn('id', $vendorProfileIds)
            ->select('id', 'public_id', 'business_name')
            ->get()
            ->keyBy('id');

        // Earliest non-expired earn expiry per (user, vendor) — one query
        $expiries = LoyaltyLedgerEntry::query()
            ->where('user_id', $userId)
            ->whereIn('vendor_profile_id', $vendorProfileIds)
            ->where('direction', 'earn')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->get(['vendor_profile_id', 'expires_at'])
            ->groupBy('vendor_profile_id')
            ->map(fn ($rows) => $rows->first()->expires_at);

        $results = [];

        foreach ($vendorProfileIds as $vendorProfileId) {
            $program = $this->programs->findByVendor((int) $vendorProfileId);
            if ($program === null || ! $program->is_active) {
                continue;
            }

            $vendor = $vendors->get($vendorProfileId);
            if ($vendor === null) {
                continue;
            }

            $available = $this->balance->availableFor($userId, (int) $vendorProfileId);

            // Decode business_name JSON if stored as a string
            $businessName = $vendor->business_name;
            if (is_string($businessName)) {
                $businessName = json_decode($businessName, true) ?? $businessName;
            }

            $pointsValueMinor = (int) ($program->points_value_minor ?? 100);

            $results[] = [
                'vendor_public_id' => (string) $vendor->public_id,
                'vendor_name' => $businessName,
                'balance_points' => $available,
                'balance_minor_equivalent' => $available * $pointsValueMinor,
                'currency' => (string) ($program->points_value_currency ?? 'EGP'),
                'expires_at' => $expiries->get($vendorProfileId),
            ];
        }

        // Sort: expires_at ASC, nulls last
        usort($results, static function (array $a, array $b): int {
            if ($a['expires_at'] === null && $b['expires_at'] === null) {
                return 0;
            }
            if ($a['expires_at'] === null) {
                return 1;
            }
            if ($b['expires_at'] === null) {
                return -1;
            }

            return $a['expires_at'] <=> $b['expires_at'];
        });

        return $results;
    }
}
