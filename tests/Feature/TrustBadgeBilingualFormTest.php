<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\TrustSafety\Domain\Models\TrustBadge;
use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages\CreateTrustBadge;
use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages\EditTrustBadge;
use Filament\Facades\Filament;
use Filament\Forms\Components\Tabs;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'create', 'update'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_trust::badge', 'web'));
    }
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('marks the Arabic tab when its required fields fail and preserves the entered English fields', function (string $locale): void {
    app()->setLocale($locale);
    $component = Livewire::test(CreateTrustBadge::class)
        ->fillForm(['name' => ['en' => 'Trusted'], 'description' => ['en' => 'Verified vendor']])
        ->call('create')
        ->assertHasFormErrors(['name.ar' => 'required', 'description.ar' => 'required'])
        ->assertFormSet(['name.en' => 'Trusted', 'description.en' => 'Verified vendor']);

    $tabs = collect($component->instance()->form->getComponents())->first(fn ($component): bool => $component instanceof Tabs)
        ->getChildComponentContainer()->getComponents();
    expect($tabs[0]->getBadge())->toBeNull()
        ->and($tabs[1]->getBadge())->toBe(__('trust-safety::trust-safety.badges.validation_errors'))
        ->and($tabs[1]->getBadgeColor())->toBe('danger')
        ->and(TrustBadge::query()->count())->toBe(0);
})->with(['en', 'ar']);

it('creates and edits both badge translations as scalar strings', function (string $locale): void {
    app()->setLocale($locale);
    $data = ['name' => ['en' => 'Trusted', 'ar' => 'موثوق'], 'description' => ['en' => 'Verified vendor', 'ar' => 'مورد معتمد'], 'level' => 1, 'is_active' => true];
    Livewire::test(CreateTrustBadge::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
    $badge = TrustBadge::query()->sole();
    expect($badge->getTranslations('name'))->toBe($data['name'])
        ->and($badge->getTranslations('description'))->toBe($data['description']);

    $data['name']['ar'] = 'موثوق ومراجع';
    Livewire::test(EditTrustBadge::class, ['record' => $badge->getRouteKey()])
        ->assertFormSet(['name.en' => 'Trusted', 'name.ar' => 'موثوق'])
        ->fillForm($data)->call('save')->assertHasNoFormErrors();
    expect($badge->refresh()->getTranslations('name'))->toBe($data['name'])
        ->and($badge->getTranslations('description'))->toBe($data['description']);
})->with(['en', 'ar']);
