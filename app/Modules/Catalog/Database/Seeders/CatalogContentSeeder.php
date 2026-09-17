<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;

/**
 * Seeds all customer-facing occasions and categories shown on the homepage.
 * Idempotent — safe to run multiple times via updateOrCreate on code.
 */
final class CatalogContentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        $this->seedOccasions();
        $this->seedCategories();
    }

    private function seedOccasions(): void
    {
        $occasions = [
            [
                'code' => 'birthday',
                'name' => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
                'description' => ['en' => 'Birthday celebrations', 'ar' => 'احتفالات أعياد الميلاد'],
                'sort_order' => 1,
                'icon_path' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (1).png',
            ],
            [
                'code' => 'baby-shower',
                'name' => ['en' => 'Baby Shower', 'ar' => 'حفلة استقبال المولود'],
                'description' => ['en' => 'Baby shower celebrations', 'ar' => 'حفلات استقبال المولود'],
                'sort_order' => 2,
                'icon_path' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (4).png',
            ],
            [
                'code' => 'graduation',
                'name' => ['en' => 'Graduation', 'ar' => 'تخرج'],
                'description' => ['en' => 'Graduation parties and ceremonies', 'ar' => 'حفلات التخرج والتكريم'],
                'sort_order' => 3,
                'icon_path' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (8).png',
            ],
            [
                'code' => 'family-gathering',
                'name' => ['en' => 'Family Gathering', 'ar' => 'تجمع عائلي'],
                'description' => ['en' => 'Family gatherings and reunions', 'ar' => 'التجمعات العائلية واللمّات'],
                'sort_order' => 4,
                'icon_path' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (7).png',
            ],
            [
                'code' => 'corporate',
                'name' => ['en' => 'Corporate Event', 'ar' => 'فعالية شركات'],
                'description' => ['en' => 'Corporate events and team celebrations', 'ar' => 'فعاليات الشركات واحتفالات الفريق'],
                'sort_order' => 5,
                'icon_path' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (9).png',
            ],
            [
                'code' => 'wedding',
                'name' => ['en' => 'Wedding', 'ar' => 'زفاف'],
                'description' => ['en' => 'Wedding ceremonies and receptions', 'ar' => 'حفلات الزفاف والاستقبال'],
                'sort_order' => 6,
                'icon_path' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (3).png',
            ],
            [
                'code' => 'engagement',
                'name' => ['en' => 'Engagement', 'ar' => 'خطوبة'],
                'description' => ['en' => 'Engagement parties and celebrations', 'ar' => 'حفلات الخطوبة والاحتفالات'],
                'sort_order' => 7,
                'icon_path' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (6).png',
            ],
        ];

        foreach ($occasions as $data) {
            $this->updateOrCreateFactoryModel(
                Occasion::factory()->active()->make([
                    'public_id' => $this->stablePublicId('occasion:'.$data['code']),
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'icon_path' => $data['icon_path'],
                    'sort_order' => $data['sort_order'],
                ]),
                ['code' => $data['code']],
            );
        }
    }

    private function seedCategories(): void
    {
        $rental = [ProductType::Rental->value];
        $sale = [ProductType::Sale->value];
        $digital = [ProductType::Digital->value];
        $all = [ProductType::Rental->value, ProductType::Sale->value, ProductType::Digital->value];

        $categories = [
            [
                'code' => 'cakes',
                'name' => ['en' => 'Cakes & Sweets', 'ar' => 'كيك وحلويات'],
                'description' => ['en' => 'Custom cakes, cupcakes, and sweet treats', 'ar' => 'تورتات وكب كيك وحلويات مخصصة'],
                'allowed_product_types' => $sale,
                'sort_order' => 1,
                'icon_path' => 'https://picsum.photos/seed/cat-cakes/400/400',
            ],
            [
                'code' => 'venues',
                'name' => ['en' => 'Venues & Halls', 'ar' => 'قاعات وأماكن'],
                'description' => ['en' => 'Event venues, halls, and outdoor spaces', 'ar' => 'قاعات فعاليات وأماكن مفتوحة'],
                'allowed_product_types' => $rental,
                'sort_order' => 2,
                'icon_path' => 'https://picsum.photos/seed/cat-venues/400/400',
            ],
            [
                'code' => 'balloons-decoration',
                'name' => ['en' => 'Balloons & Decoration', 'ar' => 'بالونات وديكور'],
                'description' => ['en' => 'Balloon arrangements and party decorations', 'ar' => 'تنسيق بالونات وديكور حفلات'],
                'allowed_product_types' => array_merge($rental, $sale),
                'sort_order' => 3,
                'icon_path' => 'https://picsum.photos/seed/cat-balloons/400/400',
            ],
            [
                'code' => 'catering',
                'name' => ['en' => 'Catering & Food', 'ar' => 'ضيافة وطعام'],
                'description' => ['en' => 'Catering services and food spreads', 'ar' => 'خدمات ضيافة وبوفيهات'],
                'allowed_product_types' => $sale,
                'sort_order' => 4,
                'icon_path' => 'https://picsum.photos/seed/cat-catering/400/400',
            ],
            [
                'code' => 'photography',
                'name' => ['en' => 'Photography & Video', 'ar' => 'تصوير وفيديو'],
                'description' => ['en' => 'Event photography and videography', 'ar' => 'تصوير فعاليات وفيديو احترافي'],
                'allowed_product_types' => array_merge($sale, $digital),
                'sort_order' => 5,
                'icon_path' => 'https://picsum.photos/seed/cat-photography/400/400',
            ],
            [
                'code' => 'entertainment',
                'name' => ['en' => 'Entertainment', 'ar' => 'ترفيه وفعاليات'],
                'description' => ['en' => 'DJs, entertainers, and live performances', 'ar' => 'دي جي ومنظمي ترفيه وعروض حية'],
                'allowed_product_types' => array_merge($rental, $sale),
                'sort_order' => 6,
                'icon_path' => 'https://picsum.photos/seed/cat-entertainment/400/400',
            ],
            [
                'code' => 'gifts',
                'name' => ['en' => 'Gifts & Favors', 'ar' => 'هدايا وتذكارات'],
                'description' => ['en' => 'Party favors, gift boxes, and e-gifts', 'ar' => 'هدايا حفلات وصناديق هدايا وبطاقات إلكترونية'],
                'allowed_product_types' => array_merge($sale, $digital),
                'sort_order' => 7,
                'icon_path' => 'https://picsum.photos/seed/cat-gifts/400/400',
            ],
            [
                'code' => 'rentals',
                'name' => ['en' => 'Party Rentals', 'ar' => 'تأجير معدات الحفلات'],
                'description' => ['en' => 'Inflatables, tents, tables, chairs and more', 'ar' => 'نطاطات وخيام وطاولات وكراسي وأكثر'],
                'allowed_product_types' => $rental,
                'sort_order' => 8,
                'icon_path' => 'https://picsum.photos/seed/cat-rentals/400/400',
            ],
            [
                'code' => 'lighting-effects',
                'name' => ['en' => 'Lighting & Effects', 'ar' => 'إضاءة ومؤثرات'],
                'description' => ['en' => 'Atmospheric lighting and special effects for events', 'ar' => 'إضاءة ومؤثرات خاصة تضيف أجواءً مميزة للمناسبات'],
                'allowed_product_types' => array_merge($rental, $sale),
                'sort_order' => 9,
                'icon_path' => 'https://picsum.photos/seed/cat-lighting-effects/400/400',
            ],
        ];

        foreach ($categories as $data) {
            $this->updateOrCreateFactoryModel(
                Category::factory()->active()->make([
                    'public_id' => $this->stablePublicId('category:'.$data['code']),
                    'parent_id' => null,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'allowed_product_types' => $data['allowed_product_types'],
                    'icon_path' => $data['icon_path'],
                    'sort_order' => $data['sort_order'],
                ]),
                ['code' => $data['code']],
            );
        }
    }
}
