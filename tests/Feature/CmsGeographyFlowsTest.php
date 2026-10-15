<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Geography\Filament\Resources\CityResource\Pages\CreateCity;
use App\Modules\Geography\Filament\Resources\CityResource\Pages\EditCity;
use App\Modules\Geography\Filament\Resources\CityResource\Pages\ListCities;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Actions\GetHomepageBlocksAction;
use App\Modules\Shared\Application\Actions\GetNavigationMenuAction;
use App\Modules\Shared\Application\Actions\PublishCmsPageAction;
use App\Modules\Shared\Application\Actions\SaveHomeBlockAction;
use App\Modules\Shared\Application\Actions\SaveNavigationMenuAction;
use App\Modules\Shared\Application\Actions\UnpublishCmsPageAction;
use App\Modules\Shared\Domain\Enums\HomeBlockType;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Filament\Resources\CmsPageResource\Pages\EditCmsPage;
use App\Modules\Shared\Filament\Resources\HomeBlockResource\Pages\EditHomeBlock;
use App\Modules\Shared\Filament\Resources\NavigationMenuResource\Pages\EditNavigationMenu;
use Database\Seeders\InformationalCmsPagesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function cmsFlowAdmin(array $abilities): User
{
    $admin = User::factory()->superAdmin()->create();
    foreach ($abilities as $ability) {
        $admin->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }

    return $admin;
}

function cmsFlowLogin(mixed $test, array $abilities): void
{
    $test->actingAs(cmsFlowAdmin($abilities));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
}

function cmsFlowHeroBlock(string $name, int $position, string $headline, string $arabicHeadline, bool $visible = true): array
{
    return [
        'block_type' => HomeBlockType::HeroCarousel->value,
        'name' => $name,
        'position' => $position,
        'is_visible' => $visible,
        'payload' => ['slides' => [[
            'headline' => ['en' => $headline, 'ar' => $arabicHeadline],
            'image_url' => 'https://example.test/cms-flow.jpg',
            'cta_label' => ['en' => 'Explore', 'ar' => 'اكتشف'],
            'cta_url' => '/cms-flow',
        ]]],
    ];
}

function cmsFlowMenuItem(string $english, string $arabic, string $url): array
{
    return [
        'label' => ['en' => $english, 'ar' => $arabic],
        'target_type' => 'external_url',
        'target_value' => $url,
        'is_visible' => true,
        'opens_in_new_tab' => false,
    ];
}

it('saves bilingual CMS edits and serves the published page in each locale', function (): void {
    $this->seed(InformationalCmsPagesSeeder::class);
    $page = CmsPage::query()->where('slug', 'about')->sole();
    cmsFlowLogin($this, ['view_any_cms::page', 'view_cms::page', 'update_cms::page']);

    Livewire::test(EditCmsPage::class, ['record' => $page->getRouteKey()])
        ->fillForm([
            'title.en' => 'Edited English page',
            'title.ar' => 'صفحة معدلة',
            'body.en' => '<p>English CMS edit.</p><script>alert(1)</script>',
            'body.ar' => '<p>تعديل صفحة المحتوى.</p>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->fresh()->getTranslation('title', 'en'))->toBe('Edited English page');
    $this->get('/en/p/about')->assertOk()->assertSee('English CMS edit.', false)->assertDontSee('<script', false);
    $this->get('/ar/p/about')->assertOk()->assertSee('تعديل صفحة المحتوى.', false);

    app(UnpublishCmsPageAction::class)->execute($page->fresh());
    $this->get('/en/p/about')->assertNotFound();
    app(PublishCmsPageAction::class)->execute($page->fresh());
    $this->get('/en/p/about')->assertOk()->assertSee('Edited English page');
});

it('saves home-block edits, ordering and visibility and renders localized content', function (): void {
    $save = app(SaveHomeBlockAction::class);
    $first = $save->execute(null, cmsFlowHeroBlock('First CMS flow', 10, 'Original hero', 'العنوان الأصلي'));
    $second = $save->execute(null, cmsFlowHeroBlock('Second CMS flow', 20, 'Second hero', 'العنوان الثاني'));
    cmsFlowLogin($this, ['view_any_home::block', 'view_home::block', 'update_home::block']);
    $payload = $first->payload;
    $payload['slides'][0]['headline']['en'] = 'Edited hero';

    Livewire::test(EditHomeBlock::class, ['record' => $first->getRouteKey()])
        ->fillForm(['name' => 'Edited CMS flow', 'position' => 30, 'payload' => $payload])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($first->fresh()->name)->toBe('Edited CMS flow')
        ->and($first->fresh()->payload['slides'][0]['headline']['en'])->toBe('Edited hero')
        ->and(array_column(app(GetHomepageBlocksAction::class)->execute('en'), 'name'))->toBe(['Second CMS flow', 'Edited CMS flow']);
    $this->get('/en')->assertOk()->assertSee('Second hero');
    $this->get('/ar')->assertOk()->assertSee('العنوان الثاني');
    $save->execute($second->fresh(), cmsFlowHeroBlock('Second CMS flow', 20, 'Second hero', 'العنوان الثاني', false));
    $arabic = app(GetHomepageBlocksAction::class)->execute('ar');
    expect(array_column($arabic, 'name'))->toBe(['Edited CMS flow'])
        ->and($arabic[0]['payload']['slides'][0]['headline'])->toBe('العنوان الأصلي')
        ->and($arabic[0]['payload']['slides'][0]['eyebrow'])->toBeNull();
    $this->get('/ar')->assertOk()->assertSee('العنوان الأصلي')->assertDontSee('العنوان الثاني');
});

it('saves edited navigation menus and exposes the new hierarchy in both locales', function (): void {
    $menu = app(SaveNavigationMenuAction::class)->execute(NavigationSlot::Header, 'CMS flow menu', [
        array_replace(cmsFlowMenuItem('Parent', 'رئيسي', 'https://example.test/parent'), [
            'children' => [cmsFlowMenuItem('Child', 'فرعي', 'https://example.test/child')],
        ]),
    ]);
    cmsFlowLogin($this, ['view_any_navigation::menu', 'view_navigation::menu', 'update_navigation::menu']);

    Livewire::test(EditNavigationMenu::class, ['record' => $menu->getRouteKey()])
        ->fillForm(['name' => 'Edited CMS flow menu'])
        ->call('save')
        ->assertHasNoFormErrors();

    foreach (['en' => 'Parent', 'ar' => 'رئيسي'] as $locale => $label) {
        $rendered = app(GetNavigationMenuAction::class)->execute(NavigationSlot::Header, $locale);
        expect($rendered['name'])->toBe('Edited CMS flow menu')
            ->and($rendered['items'][0]['label'])->toBe($label)
            ->and($rendered['items'][0]['children'][0]['target_value'])->toBe('https://example.test/child');
    }
    $this->get('/en')->assertOk()->assertSee('Parent')->assertSee('https://example.test/parent', false);
    $this->get('/ar')->assertOk()->assertSee('رئيسي')->assertSee('https://example.test/child', false);
});

it('creates, edits and deletes a bilingual city through the admin form', function (): void {
    $region = Region::factory()->create();
    $secondRegion = Region::factory()->create();
    cmsFlowLogin($this, ['view_any_city', 'view_city', 'create_city', 'update_city', 'delete_city']);
    $english = 'CMS flow city '.Str::ulid();

    Livewire::test(CreateCity::class)
        ->assertStatus(200)
        ->fillForm([
            'region_id' => (string) $region->id,
            'name.en' => $english,
            'name.ar' => 'مدينة تحقق '.Str::ulid(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $city = City::query()->where('name->en', $english)->sole();
    expect($city->governorate_id)->toBe($region->governorate_id);
    $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/customer/regions/'.$region->public_id.'/cities')
        ->assertOk()
        ->assertJsonPath('data.0.public_id', $city->public_id)
        ->assertJsonPath('data.0.name', $english);

    Livewire::test(EditCity::class, ['record' => $city->getRouteKey()])
        ->fillForm([
            'region_id' => (string) $secondRegion->id,
            'name.en' => $english.' edited',
            'name.ar' => 'مدينة معدلة',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($city->fresh()->region_id)->toBe($secondRegion->id)
        ->and($city->fresh()->governorate_id)->toBe($secondRegion->governorate_id)
        ->and($city->fresh()->getTranslation('name', 'ar', false))->toBe('مدينة معدلة');

    $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/customer/regions/'.$secondRegion->public_id.'/cities')
        ->assertOk()
        ->assertJsonPath('data.0.public_id', $city->public_id)
        ->assertJsonPath('data.0.name', $english.' edited');
    $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/customer/regions/'.$region->public_id.'/cities')
        ->assertOk()
        ->assertJsonMissing(['public_id' => $city->public_id]);
    $this->withHeaders(['Accept-Language' => 'ar'])
        ->getJson('/api/v1/customer/regions/'.$secondRegion->public_id.'/cities')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'مدينة معدلة');

    Livewire::test(ListCities::class)
        ->callTableAction('delete', $city)
        ->callMountedAction()
        ->assertHasNoTableActionErrors();
    expect(City::query()->whereKey($city->id)->exists())->toBeFalse();
});
