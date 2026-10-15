<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Database\Seeders;

use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** User-approved reference content for the disposable local demo, never real marketplace reviews. */
final class HomepagePackageReferenceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Homepage reference packages may only be seeded locally or in tests.');
        }

        $birthday = DB::table('occasions')->where('code', 'birthday')->value('id');
        if ($birthday === null) {
            throw new RuntimeException('Seed the existing birthday occasion before the reference packages.');
        }

        $source = public_path('images/homepage-scenes/package-reference.png');
        $image = file_get_contents($source);
        if ($image === false) {
            throw new RuntimeException('The user-provided package reference image is missing.');
        }

        $tags = [
            'Cake' => 'كيك', 'Balloons' => 'بالونات', 'Decor' => 'ديكور',
            'Entertainment' => 'ترفيه', 'Photo' => 'تصوير', 'Invite' => 'دعوات',
        ];
        $cards = [
            ['Classic Birthday Package', 'باقة عيد الميلاد الكلاسيكية', 'Joy Events Cairo', 'جوي إيفنتس القاهرة', 'Nasr City', 'مدينة نصر', '4.9', 250000, ['Cake', 'Balloons', 'Decor']],
            ['Kids Fun Package', 'باقة المرح للأطفال', 'Little Stars Events', 'ليتل ستارز إيفنتس', 'Maadi', 'المعادي', '4.8', 320000, ['Cake', 'Balloons', 'Entertainment']],
            ['Deluxe Party Package', 'باقة الحفل الفاخرة', 'The Party Studio', 'ذا بارتي ستوديو', 'New Cairo', 'القاهرة الجديدة', '4.9', 450000, ['Cake', 'Decor', 'Photo']],
            ['Sweet Celebration Package', 'باقة الاحتفال الحلو', 'Sugar & Co.', 'شوجر آند كو', 'Zamalek', 'الزمالك', '4.8', 300000, ['Cake', 'Decor', 'Invite']],
            ['Elegant Birthday Setup', 'تجهيز عيد ميلاد أنيق', 'Royal Events', 'رويال إيفنتس', 'Heliopolis', 'مصر الجديدة', '4.9', 550000, ['Decor', 'Balloons', 'Photo']],
            ['Balloon & Cake Package', 'باقة البالونات والكيك', 'Joy Rentals Cairo', 'جوي رينتالز القاهرة', 'Nasr City', 'مدينة نصر', '4.7', 280000, ['Cake', 'Balloons', 'Decor']],
            ['Teddy Theme Package', 'باقة حفلة الدبدوب', 'Little Moments', 'ليتل مومنتس', '6th of October', 'السادس من أكتوبر', '4.8', 360000, ['Cake', 'Decor', 'Entertainment']],
            ['Premium Invite Package', 'باقة الدعوات المميزة', 'Pixel Party Cards', 'بيكسل بارتي كاردز', 'Nasr City', 'مدينة نصر', '4.9', 180000, ['Invite', 'Decor', 'Balloons']],
            ['Family Party Package', 'باقة الحفل العائلي', 'Together Events', 'توجيذر إيفنتس', 'Maadi', 'المعادي', '4.8', 400000, ['Cake', 'Decor', 'Photo']],
            ['Full Birthday Experience', 'تجربة عيد ميلاد متكاملة', 'Event House', 'إيفنت هاوس', 'New Cairo', 'القاهرة الجديدة', '4.9', 650000, ['Cake', 'Decor', 'Entertainment']],
        ];

        DB::transaction(function () use ($cards, $tags, $birthday, $image): void {
            foreach ($cards as $index => [$name, $nameAr, $vendor, $vendorAr, $location, $locationAr, $rating, $price, $included]) {
                $package = PackageRecommendation::updateOrCreate(
                    ['slug' => 'homepage-reference-birthday-'.($index + 1)],
                    [
                        'name' => ['en' => $name, 'ar' => $nameAr],
                        'description' => ['en' => implode(', ', $included), 'ar' => implode('، ', array_map(fn ($tag) => $tags[$tag], $included))],
                        'occasion_id' => $birthday, 'min_budget_minor' => $price,
                        'max_budget_minor' => null, 'budget_currency' => 'EGP',
                        'is_published' => true, 'display_order' => $index - 100,
                    ],
                );
                $media = $package->media()->firstOrCreate(['collection_name' => 'hero'], [
                    'name' => 'User-provided homepage reference', 'file_name' => 'package-reference.png',
                    'mime_type' => 'image/png', 'disk' => 'public', 'size' => strlen($image),
                    'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [], 'responsive_images' => [],
                ]);
                $media->setCustomProperty('homepage_reference', [
                    'photo' => $index + 1,
                    'en' => ['vendor' => $vendor, 'location' => $location, 'rating' => $rating, 'tags' => $included],
                    'ar' => ['vendor' => $vendorAr, 'location' => $locationAr, 'rating' => $rating, 'tags' => array_map(fn ($tag) => $tags[$tag], $included)],
                ]);
                $media->save();
                if (! Storage::disk('public')->put($media->id.'/package-reference.png', $image)) {
                    throw new RuntimeException('Unable to store a reference package image.');
                }
            }
        });
    }
}
