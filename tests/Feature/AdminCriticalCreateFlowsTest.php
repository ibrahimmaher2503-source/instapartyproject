<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\CreateOccasion;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\CreateLoyaltyProgram;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('creates occasions categories and loyalty programs with independent translations', function (): void {
    $admin = User::factory()->superAdmin()->create();

    foreach ([
        'create_occasion', 'view_any_occasion',
        'create_category', 'view_any_category',
        'create_loyalty::program', 'view_any_loyalty::program',
    ] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateOccasion::class)
        ->fillForm([
            'code' => 'qa-occasion',
            'name.en' => 'QA Occasion',
            'name.ar' => 'مناسبة اختبار',
        ])
        ->call('create');

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'code' => 'qa-category',
            'name.en' => 'QA Category',
            'name.ar' => 'تصنيف اختبار',
            'allowed_product_types' => ['rental'],
        ])
        ->call('create');

    $vendor = VendorProfile::factory()->approved()->create();

    Livewire::test(CreateLoyaltyProgram::class)
        ->fillForm([
            'vendor_profile_id' => $vendor->id,
            'name.en' => 'QA Loyalty',
            'name.ar' => 'ولاء اختباري',
            'terms.en' => 'English terms',
            'terms.ar' => 'شروط عربية',
            'points_per_currency_unit' => 1,
            'points_value_minor' => 100,
            'points_value_currency' => 'EGP',
            'min_points_to_redeem' => 100,
            'max_redeem_pct' => 50,
        ])
        ->call('create');

    expect(Occasion::where('code', 'qa-occasion')->firstOrFail()->getTranslations('name'))
        ->toMatchArray(['en' => 'QA Occasion', 'ar' => 'مناسبة اختبار'])
        ->and(Category::where('code', 'qa-category')->firstOrFail()->getTranslations('name'))
        ->toMatchArray(['en' => 'QA Category', 'ar' => 'تصنيف اختبار'])
        ->and(LoyaltyProgram::whereBelongsTo($vendor, 'vendor')->firstOrFail()->getTranslations('name'))
        ->toMatchArray(['en' => 'QA Loyalty', 'ar' => 'ولاء اختباري']);

    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);

        $this->get('/admin/loyalty-programs')
            ->assertOk()
            ->assertDontSee('loyalty.columns.')
            ->assertDontSee('????');

        expect(__('shared::admin.nav.groups.operations'))->not->toContain('?');
    }
});
