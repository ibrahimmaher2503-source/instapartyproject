<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Actions\GetNavigationMenuAction;
use App\Modules\Shared\Application\Actions\PublishCmsPageAction;
use App\Modules\Shared\Application\Actions\UnpublishCmsPageAction;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Enums\NavigationTargetType;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Domain\Models\NavigationMenu;
use App\Modules\Shared\Domain\Models\NavigationMenuItem;
use App\Modules\Shared\Filament\Forms\BilingualTabErrorFeedback;
use App\Modules\Shared\Filament\Resources\CmsPageResource\Pages\EditCmsPage;
use Database\Seeders\InformationalCmsPagesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function qaVerifyCmsAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(
        Permission::findOrCreate('view_any_cms::page', 'web'),
        Permission::findOrCreate('view_cms::page', 'web'),
        Permission::findOrCreate('update_cms::page', 'web'),
    );

    return $admin;
}

it('lets an admin set a public CMS slug while preserving canonical pages and menu destinations', function (): void {
    $this->seed(InformationalCmsPagesSeeder::class);
    $page = CmsPage::query()->where('slug', 'about')->sole();
    $menu = NavigationMenu::query()->create([
        'public_id' => (string) Str::ulid(),
        'slot' => NavigationSlot::Header,
        'name' => 'QA CMS links',
    ]);
    NavigationMenuItem::query()->create([
        'public_id' => (string) Str::ulid(),
        'menu_id' => $menu->id,
        'parent_id' => null,
        'label' => ['en' => 'About', 'ar' => 'من نحن'],
        'target_type' => NavigationTargetType::CmsPage,
        'target_value' => 'about',
        'is_visible' => true,
        'opens_in_new_tab' => false,
        'position' => 1,
    ]);
    app(GetNavigationMenuAction::class)->execute(NavigationSlot::Header, 'en');

    $this->actingAs(qaVerifyCmsAdmin());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditCmsPage::class, ['record' => $page->getRouteKey()])
        ->assertSuccessful()
        ->fillForm([
            'public_slug' => 'about-qa-guide',
            'title.en' => 'About QA',
            'title.ar' => 'عن الاختبار',
            'body.en' => '<p>English QA body.</p>',
            'body.ar' => '<p>محتوى عربي للاختبار.</p>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->public_slug)->toBe('about-qa-guide')
        ->and($page->getTranslation('title', 'ar'))->toBe('عن الاختبار');

    $this->get('/en/p/about-qa-guide')->assertOk()->assertSee('English QA body.', false);
    $this->get('/ar/p/about-qa-guide')->assertOk()->assertSee('محتوى عربي للاختبار.', false);
    $this->get('/en/p/about')->assertOk()->assertSee('English QA body.', false);
    $this->get('/en/p/about-qa-guide')->assertSee('/en/p/about-qa-guide', false);

    app(UnpublishCmsPageAction::class)->execute($page->refresh());
    $this->get('/en/p/about-qa-guide')->assertNotFound();
    $this->get('/en/p/about')->assertNotFound();
    app(PublishCmsPageAction::class)->execute($page->refresh());
    $this->get('/en/p/about-qa-guide')->assertOk();
});

it('rejects duplicate and reserved CMS public slugs without changing the saved page', function (): void {
    $this->seed(InformationalCmsPagesSeeder::class);
    $about = CmsPage::query()->where('slug', 'about')->sole();
    $privacy = CmsPage::query()->where('slug', 'privacy')->sole();
    $about->public_slug = 'about-qa-guide';
    $about->save();
    $this->actingAs(qaVerifyCmsAdmin());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditCmsPage::class, ['record' => $about->getRouteKey()])
        ->fillForm(['public_slug' => 'privacy'])
        ->call('save')
        ->assertHasFormErrors(['public_slug']);

    Livewire::test(EditCmsPage::class, ['record' => $privacy->getRouteKey()])
        ->fillForm(['public_slug' => 'about-qa-guide'])
        ->call('save')
        ->assertHasFormErrors(['public_slug']);

    Livewire::test(EditCmsPage::class, ['record' => $privacy->getRouteKey()])
        ->fillForm(['public_slug' => 'faq'])
        ->call('save')
        ->assertHasFormErrors(['public_slug']);

    expect($about->refresh()->public_slug)->toBe('about-qa-guide')
        ->and($privacy->refresh()->public_slug)->toBeNull();
});

it('activates and identifies the language tab containing CMS validation errors', function (): void {
    $this->seed(InformationalCmsPagesSeeder::class);
    $page = CmsPage::query()->where('slug', 'about')->sole();
    $this->actingAs(qaVerifyCmsAdmin());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $form = Livewire::test(EditCmsPage::class, ['record' => $page->getRouteKey()])
        ->fillForm([
            'title.en' => '',
            'title.ar' => '',
            'body.en' => '<p>English body</p>',
            'body.ar' => '<p>محتوى عربي</p>',
        ])
        ->call('save')
        ->assertHasFormErrors(['title.en', 'title.ar']);

    expect(BilingualTabErrorFeedback::activeTab($form->instance()))->toBe(1)
        ->and(BilingualTabErrorFeedback::countForLocale($form->instance(), 'en'))->toBeGreaterThan(0)
        ->and(BilingualTabErrorFeedback::countForLocale($form->instance(), 'ar'))->toBeGreaterThan(0);
});
