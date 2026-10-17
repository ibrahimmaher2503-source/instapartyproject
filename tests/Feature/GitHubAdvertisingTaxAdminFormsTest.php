<?php

declare(strict_types=1);

use App\Modules\Advertising\Domain\Enums\PlacementType;
use App\Modules\Advertising\Domain\Models\AdvertisementPackage;
use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource;
use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource\Pages\CreateAdvertisementPackage;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tax\Domain\Enums\TaxAppliesTo;
use App\Modules\Tax\Domain\Models\TaxRate;
use App\Modules\Tax\Filament\Resources\TaxRateResource;
use App\Modules\Tax\Filament\Resources\TaxRateResource\Pages\CreateTaxRate;
use App\Modules\Tax\Filament\Resources\TaxRateResource\Pages\EditTaxRate;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('creates an advertisement package from its authorized bilingual Filament form', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_advertisement::package', 'web'),
        Permission::findOrCreate('create_advertisement::package', 'web'),
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get(AdvertisementPackageResource::getUrl('create'))->assertOk();

    Livewire::test(CreateAdvertisementPackage::class)
        ->fillForm([
            'name.en' => 'Summer Campaign',
            'name.ar' => 'حملة الصيف',
            'description.en' => 'English package description',
            'description.ar' => 'وصف الباقة بالعربية',
            'placement_type' => PlacementType::HomepageBanner->value,
            'duration_days' => 30,
            'price_minor' => 50000,
            'impression_limit' => 10000,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $package = AdvertisementPackage::query()->where('name->en', 'Summer Campaign')->firstOrFail();

    expect($package->getTranslations('name'))
        ->toMatchArray(['en' => 'Summer Campaign', 'ar' => 'حملة الصيف'])
        ->and($package->getTranslations('description'))
        ->toMatchArray(['en' => 'English package description', 'ar' => 'وصف الباقة بالعربية'])
        ->and($package->public_id)->toHaveLength(26);
});

it('creates customer and all tax rates with the supplied percentages and translations', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_tax::rate', 'web'),
        Permission::findOrCreate('create_tax::rate', 'web'),
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get(TaxRateResource::getUrl('create'))->assertOk();

    foreach ([
        [TaxAppliesTo::Customer, 5, 'Customer tax', 'ضريبة العملاء'],
        [TaxAppliesTo::All, 10, 'All tax', 'ضريبة الجميع'],
    ] as [$appliesTo, $percentage, $nameEn, $nameAr]) {
        Livewire::test(CreateTaxRate::class)
            ->fillForm([
                'name.en' => $nameEn,
                'name.ar' => $nameAr,
                'description.en' => 'English tax description',
                'description.ar' => 'وصف الضريبة بالعربية',
                'rate_bps' => (string) $percentage,
                'applies_to' => $appliesTo->value,
                'product_types' => [],
                'is_tax_inclusive' => false,
                'is_active' => true,
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    $customerRate = TaxRate::query()->where('name->en', 'Customer tax')->firstOrFail();
    $allRate = TaxRate::query()->where('name->en', 'All tax')->firstOrFail();

    expect($customerRate->rate_bps)->toBe(500)
        ->and($customerRate->applies_to)->toBe(TaxAppliesTo::Customer)
        ->and($customerRate->getTranslations('name'))->toMatchArray(['en' => 'Customer tax', 'ar' => 'ضريبة العملاء'])
        ->and($allRate->rate_bps)->toBe(1000)
        ->and($allRate->applies_to)->toBe(TaxAppliesTo::All)
        ->and($allRate->getTranslations('name'))->toMatchArray(['en' => 'All tax', 'ar' => 'ضريبة الجميع'])
        ->and($customerRate->public_id)->toHaveLength(26)
        ->and($allRate->public_id)->toHaveLength(26);
});

it('shows field-level validation feedback for an empty tax-rate create form', function (): void {
    app()->setLocale('en');

    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_tax::rate', 'web'),
        Permission::findOrCreate('create_tax::rate', 'web'),
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $component = Livewire::test(CreateTaxRate::class);
    $fields = collect($component->instance()->form->getFlatComponents())
        ->filter(fn ($field): bool => method_exists($field, 'getName'))
        ->keyBy(fn ($field): string => $field->getName());

    foreach (['name.en', 'name.ar', 'rate_bps', 'applies_to', 'effective_from'] as $name) {
        expect($fields)->toHaveKey($name)
            ->and($fields[$name]->isRequired())->toBeFalse()
            ->and($fields[$name]->isMarkedAsRequired())->toBeTrue();
    }

    $component
        ->call('create')
        ->assertHasFormErrors([
            'name.en' => 'required',
            'name.ar' => 'required',
            'rate_bps' => 'required',
            'applies_to' => 'required',
            'effective_from' => 'required',
        ])
        ->assertSeeText('The name (EN) field is required.')
        ->assertSeeText('The name (AR) field is required.')
        ->assertSeeText('The rate (%) field is required.')
        ->assertSeeText('The applies To field is required.')
        ->assertSeeText('The effective From field is required.');

    expect(TaxRate::query()->count())->toBe(0);
});

it('hydrates and saves both existing tax-rate translations', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('view_any_tax::rate', 'web'),
        Permission::findOrCreate('view_tax::rate', 'web'),
        Permission::findOrCreate('update_tax::rate', 'web'),
    ]);

    $taxRate = TaxRate::query()->create([
        'public_id' => (string) Str::ulid(),
        'name' => ['en' => 'Existing customer tax', 'ar' => 'ضريبة العملاء الحالية'],
        'description' => ['en' => 'English description', 'ar' => 'وصف عربي'],
        'rate_bps' => 500,
        'applies_to' => TaxAppliesTo::Customer,
        'product_types' => null,
        'is_tax_inclusive' => false,
        'is_active' => true,
        'effective_from' => now()->toDateString(),
        'effective_to' => null,
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get(TaxRateResource::getUrl('edit', ['record' => $taxRate]))->assertOk();

    Livewire::test(EditTaxRate::class, ['record' => $taxRate->getRouteKey()])
        ->assertOk()
        ->assertFormSet([
            'name.en' => 'Existing customer tax',
            'name.ar' => 'ضريبة العملاء الحالية',
            'description.en' => 'English description',
            'description.ar' => 'وصف عربي',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($taxRate->fresh()->getTranslations('name'))
        ->toMatchArray(['en' => 'Existing customer tax', 'ar' => 'ضريبة العملاء الحالية'])
        ->and($taxRate->fresh()->getTranslations('description'))
        ->toMatchArray(['en' => 'English description', 'ar' => 'وصف عربي']);
});

it('keeps advertisement-package and tax-rate form routes permission gated', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get(AdvertisementPackageResource::getUrl('create'))->assertForbidden();
    $this->get(TaxRateResource::getUrl('create'))->assertForbidden();
});
