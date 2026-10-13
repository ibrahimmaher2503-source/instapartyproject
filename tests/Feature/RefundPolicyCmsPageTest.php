<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Enums\NavigationTargetType;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Domain\Models\NavigationMenu;
use App\Modules\Shared\Domain\Models\NavigationMenuItem;
use App\Modules\Shared\Filament\Resources\CmsPageResource\Pages\EditCmsPage;
use Database\Seeders\RefundPolicyCmsPageSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('renders the editable refund policy in both storefront locales', function (): void {
    $this->seed(RefundPolicyCmsPageSeeder::class);

    $this->get('/en/p/refund-policy')
        ->assertOk()
        ->assertSee('Refunds and cancellations')
        ->assertSee('The Egyptian Consumer Protection Agency describes', false)
        ->assertSee('ArticleID/6790', false);

    $this->get('/ar/p/refund-policy')
        ->assertOk()
        ->assertSee('سياسة الاسترداد والإلغاء')
        ->assertSee('جهاز حماية المستهلك');
});

it('lets an admin edit the policy through the existing CMS resource', function (): void {
    $this->seed(RefundPolicyCmsPageSeeder::class);
    $page = CmsPage::query()->where('slug', 'refund-policy')->sole();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditCmsPage::class, ['record' => $page->getRouteKey()])
        ->fillForm([
            'title.en' => 'Updated refund policy',
            'title.ar' => 'سياسة استرداد محدثة',
            'body.en' => '<h2>Updated section</h2><p>Updated English policy.</p>',
            'body.ar' => '<h2>قسم محدث</h2><p>سياسة محدثة باللغة العربية.</p>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->getTranslation('title', 'en'))->toBe('Updated refund policy')
        ->and($page->getTranslation('body', 'ar'))->toContain('سياسة محدثة باللغة العربية.');
});

it('preserves CMS edits and publication state when the additive migration is rerun', function (): void {
    $this->seed(RefundPolicyCmsPageSeeder::class);
    $page = CmsPage::query()->where('slug', 'refund-policy')->sole();
    $menu = NavigationMenu::query()->create([
        'public_id' => (string) Str::ulid(),
        'slot' => NavigationSlot::FooterSecondary,
        'name' => 'Legal pages',
    ]);
    $page->setTranslation('title', 'en', 'Editorially approved title');
    $page->setTranslation('body', 'ar', '<h2>تحرير مخصص</h2><p>النص المحرر.</p>');
    $page->forceFill(['is_published' => false, 'published_at' => null])->save();

    $migration = require base_path('app/Modules/Shared/Database/Migrations/2026_10_13_120000_seed_refund_policy_cms_page.php');
    $migration->up();

    $page->refresh();

    expect($page->getTranslation('title', 'en'))->toBe('Editorially approved title')
        ->and($page->getTranslation('body', 'ar'))->toContain('النص المحرر.')
        ->and($page->is_published)->toBeFalse()
        ->and($page->published_at)->toBeNull()
        ->and(NavigationMenuItem::query()
            ->where('menu_id', $menu->id)
            ->where('target_value', 'refund-policy')
            ->exists())->toBeFalse();

    $this->get('/en/p/refund-policy')->assertNotFound();
});

it('appends the footer link without replacing existing legal links', function (): void {
    $menu = NavigationMenu::query()->create([
        'public_id' => (string) Str::ulid(),
        'slot' => NavigationSlot::FooterSecondary,
        'name' => 'Legal pages',
    ]);
    $terms = NavigationMenuItem::query()->create([
        'public_id' => (string) Str::ulid(),
        'menu_id' => $menu->id,
        'parent_id' => null,
        'label' => ['en' => 'Terms', 'ar' => 'الشروط'],
        'target_type' => NavigationTargetType::CmsPage,
        'target_value' => 'terms',
        'is_visible' => true,
        'opens_in_new_tab' => false,
        'position' => 1,
    ]);

    $this->seed(RefundPolicyCmsPageSeeder::class);
    $this->seed(RefundPolicyCmsPageSeeder::class);

    $policyLink = NavigationMenuItem::query()
        ->where('menu_id', $menu->id)
        ->where('target_value', 'refund-policy')
        ->sole();

    expect($terms->refresh()->target_value)->toBe('terms')
        ->and($policyLink->target_type)->toBe(NavigationTargetType::CmsPage)
        ->and($policyLink->label)->toBe(['en' => 'Refunds and cancellations', 'ar' => 'الاسترداد والإلغاء'])
        ->and($policyLink->position)->toBe(2)
        ->and(NavigationMenuItem::query()->where('target_value', 'refund-policy')->count())->toBe(1);
});
