<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Database\Seeders;

use App\Modules\Advertising\Domain\Enums\PlacementType;
use App\Modules\Advertising\Domain\Models\AdvertisementPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdvertisementPackageDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => ['en' => 'Homepage Hero — 7 days', 'ar' => 'بانر الصفحة الرئيسية — 7 أيام'],
                'description' => ['en' => 'Premium homepage hero banner placement for one week.', 'ar' => 'بانر مميز في الصفحة الرئيسية لمدة أسبوع.'],
                'placement_type' => PlacementType::HomepageBanner,
                'duration_days' => 7,
                'price_minor' => 500_00,
                'impression_limit' => 50_000,
            ],
            [
                'name' => ['en' => 'Category Banner — 14 days', 'ar' => 'بانر فئة — 14 يومًا'],
                'description' => ['en' => 'Banner shown at top of a single category page for two weeks.', 'ar' => 'بانر يظهر أعلى صفحة فئة واحدة لمدة أسبوعين.'],
                'placement_type' => PlacementType::CategoryBanner,
                'duration_days' => 14,
                'price_minor' => 300_00,
                'impression_limit' => 20_000,
            ],
            [
                'name' => ['en' => 'Featured Listing — 30 days', 'ar' => 'إعلان مميز — 30 يومًا'],
                'description' => ['en' => 'Boost a single service to top of search results for one month.', 'ar' => 'تعزيز خدمة واحدة في أعلى نتائج البحث لمدة شهر.'],
                'placement_type' => PlacementType::FeaturedListing,
                'duration_days' => 30,
                'price_minor' => 150_00,
                'impression_limit' => null,
            ],
            [
                'name' => ['en' => 'Sponsored Search — 30 days', 'ar' => 'بحث برعاية — 30 يومًا'],
                'description' => ['en' => 'Sponsored slot in search for one month.', 'ar' => 'مكان برعاية في البحث لمدة شهر.'],
                'placement_type' => PlacementType::SearchSponsored,
                'duration_days' => 30,
                'price_minor' => 250_00,
                'impression_limit' => 100_000,
            ],
        ];

        foreach ($rows as $row) {
            AdvertisementPackage::query()->updateOrCreate(
                ['placement_type' => $row['placement_type'], 'duration_days' => $row['duration_days']],
                array_merge($row, [
                    'public_id' => (string) Str::ulid(),
                    'price_currency' => 'EGP',
                    'is_active' => true,
                ]),
            );
        }
    }
}
