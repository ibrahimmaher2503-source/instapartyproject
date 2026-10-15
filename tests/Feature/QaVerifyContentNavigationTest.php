<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Actions\GetNavigationMenuAction;
use App\Modules\Shared\Application\Actions\SaveNavigationMenuAction;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Filament\Resources\NavigationMenuResource\Pages\EditNavigationMenu;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function qaVerifyNavigationItem(string $english, string $arabic, string $target): array
{
    return [
        'label' => ['en' => $english, 'ar' => $arabic],
        'target_type' => 'external_url',
        'target_value' => $target,
        'is_visible' => true,
        'opens_in_new_tab' => true,
    ];
}

it('renders saved menu labels in both locales and omits hidden links', function (): void {
    app(SaveNavigationMenuAction::class)->execute(NavigationSlot::FooterSecondary, 'QA footer', [
        qaVerifyNavigationItem('First link', 'الرابط الأول', 'https://example.test/first'),
        array_replace(qaVerifyNavigationItem('Hidden link', 'رابط مخفي', 'https://example.test/hidden'), ['is_visible' => false]),
        qaVerifyNavigationItem('Second link', 'الرابط الثاني', 'https://example.test/second'),
    ]);

    $english = app(GetNavigationMenuAction::class)->execute(NavigationSlot::FooterSecondary, 'en');
    $arabic = app(GetNavigationMenuAction::class)->execute(NavigationSlot::FooterSecondary, 'ar');

    expect(array_column($english['items'], 'label'))->toBe(['First link', 'Second link'])
        ->and(array_column($arabic['items'], 'label'))->toBe(['الرابط الأول', 'الرابط الثاني']);
});

it('rejects an unsafe destination before replacing the saved menu', function (): void {
    $action = app(SaveNavigationMenuAction::class);
    $saved = $action->execute(NavigationSlot::Header, 'QA header', [
        qaVerifyNavigationItem('Safe link', 'رابط آمن', 'https://example.test/safe'),
    ]);

    expect(fn () => $action->execute(NavigationSlot::Header, 'QA header edited', [
        qaVerifyNavigationItem('Unsafe link', 'رابط غير آمن', 'javascript:alert(1)'),
    ]))->toThrow(ValidationException::class);

    expect($saved->fresh()->name)->toBe('QA header')
        ->and($saved->fresh()->items()->sole()->target_value)->toBe('https://example.test/safe');
});

it('saves the actual edit form without submitting the immutable slot', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'update'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_navigation::menu', 'web'));
    }
    $this->actingAs($admin);
    $menu = app(SaveNavigationMenuAction::class)->execute(NavigationSlot::FooterSecondary, 'Original menu', [
        array_replace(qaVerifyNavigationItem('Link', 'رابط', 'https://example.test/safe'), [
            'children' => [qaVerifyNavigationItem('Child', 'فرعي', 'https://example.test/child')],
        ]),
        qaVerifyNavigationItem('Link', 'رابط', 'https://example.test/second'),
    ]);
    foreach (['en', 'ar'] as $locale) {
        app(GetNavigationMenuAction::class)->execute(NavigationSlot::FooterSecondary, $locale);
    }

    Livewire::test(EditNavigationMenu::class, ['record' => $menu->public_id])
        ->fillForm(['name' => 'Edited menu'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($menu->fresh()->name)->toBe('Edited menu')
        ->and($menu->fresh()->slot)->toBe(NavigationSlot::FooterSecondary)
        ->and($menu->fresh()->rootItems()->pluck('target_value')->all())->toBe(['https://example.test/safe', 'https://example.test/second'])
        ->and($menu->fresh()->rootItems()->firstOrFail()->children()->sole()->target_value)->toBe('https://example.test/child');
    foreach (['en', 'ar'] as $locale) {
        expect(app(GetNavigationMenuAction::class)->execute(NavigationSlot::FooterSecondary, $locale)['name'])->toBe('Edited menu');
    }
});
