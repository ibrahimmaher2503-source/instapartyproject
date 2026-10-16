<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Filament\Resources\CmsPageResource\Pages\EditCmsPage;
use Database\Seeders\InformationalCmsPagesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->seed(InformationalCmsPagesSeeder::class);
});

function createCmsPageAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(
        Permission::findOrCreate('view_any_cms::page', 'web'),
        Permission::findOrCreate('view_cms::page', 'web'),
        Permission::findOrCreate('update_cms::page', 'web'),
    );

    return $admin;
}

it('renders the Arabic tab label as العربية on CMS Pages edit form without mojibake', function (): void {
    $page = CmsPage::query()->where('slug', 'about')->sole();
    $admin = createCmsPageAdmin();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditCmsPage::class, ['record' => $page->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('العربية')
        ->assertSee('English')
        ->assertDontSee('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©');
});

it('supports editing Arabic content and preserves Arabic text encoding', function (): void {
    $page = CmsPage::query()->where('slug', 'about')->sole();
    $admin = createCmsPageAdmin();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditCmsPage::class, ['record' => $page->getRouteKey()])
        ->fillForm([
            'title.en' => 'About Us Updated',
            'title.ar' => 'من نحن المحدثة',
            'body.en' => '<p>Updated English body</p>',
            'body.ar' => '<p>محتوى عربي محدث بدون أخطاء ترميز</p>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();

    expect($page->getTranslation('title', 'ar'))->toBe('من نحن المحدثة')
        ->and($page->getTranslation('body', 'ar'))->toContain('محتوى عربي محدث بدون أخطاء ترميز');
});

it('activates error feedback tab when Arabic field fails validation', function (): void {
    $page = CmsPage::query()->where('slug', 'about')->sole();
    $admin = createCmsPageAdmin();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditCmsPage::class, ['record' => $page->getRouteKey()])
        ->fillForm([
            'title.en' => 'Valid English Title',
            'title.ar' => '',
            'body.en' => '<p>Valid body</p>',
            'body.ar' => '<p>Valid arabic body</p>',
        ])
        ->call('save')
        ->assertHasFormErrors(['title.ar']);
});
