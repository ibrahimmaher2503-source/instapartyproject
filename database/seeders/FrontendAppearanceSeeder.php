<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Shared\Domain\Enums\HomeBlockType;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Enums\NavigationTargetType;
use App\Modules\Shared\Domain\Models\BrandingSetting;
use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Domain\Models\HomeBlock;
use App\Modules\Shared\Domain\Models\NavigationMenu;
use App\Modules\Shared\Domain\Models\NavigationMenuItem;
use App\Modules\Shared\Domain\Schemas\DesignTokenSchema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FrontendAppearanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CmsPagesSeeder::class);

        DesignToken::query()->where('name', '!=', 'default')->update(['is_active' => false]);

        DesignToken::query()->updateOrCreate(
            ['name' => 'default'],
            [
                'public_id' => DesignToken::query()->where('name', 'default')->value('public_id') ?? (string) Str::ulid(),
                'is_active' => true,
                'tokens' => DesignTokenSchema::default(),
            ],
        );

        $branding = BrandingSetting::current();
        $branding->fill([
            'site_name' => ['en' => 'InstaParty', 'ar' => 'إنستابارتي'],
            'tagline' => ['en' => 'Everything for memorable events in one place.', 'ar' => 'كل ما تحتاجه لمناسبتك في مكان واحد.'],
            'address_line' => ['en' => 'Benha, Qalyubia, Egypt', 'ar' => 'بنها، القليوبية، مصر'],
            'support_email' => filled($branding->support_email) && ! str_ends_with(strtolower($branding->support_email), '.local')
                ? $branding->support_email
                : 'support@instaparty.eg',
            'support_phone' => '+201000000000',
            'whatsapp_number' => '+201000000000',
            'social' => ['instagram' => 'https://instagram.com/instaparty'],
        ])->save();

        $this->seedMenus();
        $this->seedHomeBlocks();

        Cache::flush();
    }

    private function seedMenus(): void
    {
        $header = $this->menu(NavigationSlot::Header, 'Header');
        $this->item($header, 1, ['en' => 'Explore', 'ar' => 'استكشف'], NavigationTargetType::InternalPath, '/search');
        $this->item($header, 2, ['en' => 'Vendors', 'ar' => 'البائعون'], NavigationTargetType::InternalPath, '/vendors');
        $this->item($header, 3, ['en' => 'Plan an event', 'ar' => 'خطط لمناسبتك'], NavigationTargetType::InternalPath, '/wizard');
        $this->item($header, 4, ['en' => 'About us', 'ar' => 'من نحن'], NavigationTargetType::CmsPage, 'about');
        $this->item($header, 5, ['en' => 'Join as vendor', 'ar' => 'انضم كبائع'], NavigationTargetType::InternalPath, '/join-us');

        $footerPrimary = $this->menu(NavigationSlot::FooterPrimary, 'Footer primary');
        $this->item($footerPrimary, 1, ['en' => 'About', 'ar' => 'من نحن'], NavigationTargetType::CmsPage, 'about');
        $this->item($footerPrimary, 2, ['en' => 'Contact', 'ar' => 'اتصل بنا'], NavigationTargetType::CmsPage, 'contact');

        $footerSecondary = $this->menu(NavigationSlot::FooterSecondary, 'Footer secondary');
        $this->item($footerSecondary, 1, ['en' => 'Terms', 'ar' => 'الشروط'], NavigationTargetType::CmsPage, 'terms');
        $this->item($footerSecondary, 2, ['en' => 'Privacy', 'ar' => 'الخصوصية'], NavigationTargetType::CmsPage, 'privacy');

        $mobile = $this->menu(NavigationSlot::MobileDrawer, 'Mobile drawer');
        $this->item($mobile, 1, ['en' => 'Home', 'ar' => 'الرئيسية'], NavigationTargetType::InternalPath, '/');
        $this->item($mobile, 2, ['en' => 'Explore', 'ar' => 'استكشف'], NavigationTargetType::InternalPath, '/search');
        $this->item($mobile, 3, ['en' => 'Vendors', 'ar' => 'البائعون'], NavigationTargetType::InternalPath, '/vendors');
        $this->item($mobile, 4, ['en' => 'Plan an event', 'ar' => 'خطط لمناسبتك'], NavigationTargetType::InternalPath, '/wizard');
        $this->item($mobile, 5, ['en' => 'Join as vendor', 'ar' => 'انضم كبائع'], NavigationTargetType::InternalPath, '/join-us');
    }

    private function seedHomeBlocks(): void
    {
        $serviceIds = Service::query()->published()->orderBy('id')->limit(4)->pluck('public_id')->all();
        $categoryIds = Category::query()->active()->orderBy('sort_order')->limit(8)->pluck('public_id');
        $categoryIds = $categoryIds
            ->merge(Category::query()->active()->where('code', 'lighting-effects')->pluck('public_id'))
            ->unique()
            ->values()
            ->all();
        $occasionIds = Occasion::query()->where('is_active', true)->orderBy('sort_order')->limit(6)->pluck('public_id')->all();

        HomeBlock::query()->updateOrCreate(
            ['name' => 'Homepage hero'],
            [
                'public_id' => HomeBlock::query()->where('name', 'Homepage hero')->value('public_id') ?? (string) Str::ulid(),
                'block_type' => HomeBlockType::HeroCarousel,
                'position' => 1,
                'is_visible' => true,
                'payload' => [
                    'slides' => [[
                        'eyebrow' => ['en' => 'Egypt’s celebration marketplace', 'ar' => 'سوق الاحتفالات في مصر'],
                        'headline' => ['en' => 'Premium, modern party planning and booking', 'ar' => 'تخطيط وحجز عصري ومميز لمناسبتك'],
                        'sub' => ['en' => 'Everything you need to plan the perfect event. Fast, easy, and in one place.', 'ar' => 'كل ما تحتاجه لتخطيط مناسبة مثالية بسهولة وسرعة وفي مكان واحد.'],
                        'cta_label' => ['en' => 'Start planning your party', 'ar' => 'ابدأ تخطيط مناسبتك'],
                        'cta_url' => '/wizard',
                    ]],
                ],
            ],
        );

        HomeBlock::query()->updateOrCreate(
            ['name' => 'Featured services'],
            [
                'public_id' => HomeBlock::query()->where('name', 'Featured services')->value('public_id') ?? (string) Str::ulid(),
                'block_type' => HomeBlockType::FeaturedServices,
                'position' => 2,
                'is_visible' => count($serviceIds) > 0,
                'payload' => ['title' => ['en' => 'Popular services', 'ar' => 'خدمات مميزة'], 'service_public_ids' => $serviceIds],
            ],
        );

        HomeBlock::query()->updateOrCreate(
            ['name' => 'Featured occasions'],
            [
                'public_id' => HomeBlock::query()->where('name', 'Featured occasions')->value('public_id') ?? (string) Str::ulid(),
                'block_type' => HomeBlockType::FeaturedOccasions,
                'position' => 3,
                'is_visible' => count($occasionIds) > 0,
                'payload' => ['title' => ['en' => 'Start with an occasion', 'ar' => 'ابدأ بالمناسبة'], 'public_ids' => $occasionIds],
            ],
        );

        HomeBlock::query()->updateOrCreate(
            ['name' => 'Featured categories'],
            [
                'public_id' => HomeBlock::query()->where('name', 'Featured categories')->value('public_id') ?? (string) Str::ulid(),
                'block_type' => HomeBlockType::FeaturedCategories,
                'position' => 4,
                'is_visible' => count($categoryIds) > 0,
                'payload' => ['title' => ['en' => 'Shop by category', 'ar' => 'تسوق حسب التصنيف'], 'public_ids' => $categoryIds],
            ],
        );

        HomeBlock::query()->updateOrCreate(
            ['name' => 'Vendor join'],
            [
                'public_id' => HomeBlock::query()->where('name', 'Vendor join')->value('public_id') ?? (string) Str::ulid(),
                'block_type' => HomeBlockType::VendorJoin,
                'position' => 5,
                'is_visible' => true,
                'payload' => [
                    'headline' => [
                        'en' => trans('storefront.join_us.title', [], 'en'),
                        'ar' => trans('storefront.join_us.title', [], 'ar'),
                    ],
                    'body' => [
                        'en' => trans('storefront.join_us.intro', [], 'en'),
                        'ar' => trans('storefront.join_us.intro', [], 'ar'),
                    ],
                    'cta_label' => [
                        'en' => trans('storefront.join_us.primary_cta', [], 'en'),
                        'ar' => trans('storefront.join_us.primary_cta', [], 'ar'),
                    ],
                    'cta_url' => '/join-us',
                ],
            ],
        );
    }

    private function menu(NavigationSlot $slot, string $name): NavigationMenu
    {
        return NavigationMenu::query()->updateOrCreate(
            ['slot' => $slot->value],
            [
                'public_id' => NavigationMenu::query()->where('slot', $slot->value)->value('public_id') ?? (string) Str::ulid(),
                'name' => $name,
            ],
        );
    }

    /** @param array<string, string> $label */
    private function item(NavigationMenu $menu, int $position, array $label, NavigationTargetType $type, string $target): void
    {
        NavigationMenuItem::query()->updateOrCreate(
            ['menu_id' => $menu->id, 'position' => $position, 'parent_id' => null],
            [
                'public_id' => NavigationMenuItem::query()
                    ->where('menu_id', $menu->id)
                    ->where('position', $position)
                    ->whereNull('parent_id')
                    ->value('public_id') ?? (string) Str::ulid(),
                'label' => $label,
                'target_type' => $type,
                'target_value' => $target,
                'is_visible' => true,
                'opens_in_new_tab' => false,
            ],
        );
    }
}
