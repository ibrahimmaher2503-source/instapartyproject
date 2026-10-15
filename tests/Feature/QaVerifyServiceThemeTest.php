<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Database\Seeders\CatalogServiceThemePermissionsSeeder;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Catalog\Domain\Policies\ServiceThemePolicy;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages\EditDigitalService;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\EditRentalService;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\EditSaleService;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\User as Customer;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('registers the service theme policy and seeds its resource permissions for administrators', function (): void {
    app(CatalogServiceThemePermissionsSeeder::class)->run();

    $admin = User::factory()->asAdmin()->create();

    expect(Gate::getPolicyFor(ServiceTheme::class))->toBeInstanceOf(ServiceThemePolicy::class)
        ->and($admin->can('viewAny', ServiceTheme::class))->toBeTrue()
        ->and($admin->can('create', ServiceTheme::class))->toBeTrue()
        ->and($admin->can('deleteAny', ServiceTheme::class))->toBeTrue();
});

it('assigns active bilingual themes to each service type and exposes only active assignments publicly', function (ProductType $type, string $locale): void {
    config(['catalog.digital_services_enabled' => true]);
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'update'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_'.$type->value.'::service', 'web'));
    }
    $this->actingAs($admin);

    $factory = match ($type) {
        ProductType::Rental => Service::factory()->rental(),
        ProductType::Sale => Service::factory()->sale(),
        ProductType::Digital => Service::factory()->digital(),
    };
    $service = $factory->published()->create();
    if ($type === ProductType::Rental) {
        ServiceRentalDetail::factory()->create(['service_id' => $service->id]);
    } elseif ($type === ProductType::Sale) {
        ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'customization_fields' => []]);
    } else {
        ServiceDigitalDetail::factory()->create(['service_id' => $service->id, 'delivery_method' => 'email']);
    }
    $active = ServiceTheme::factory()->create([
        'code' => 'qa-bilingual-theme',
        'name' => ['en' => 'Garden Party', 'ar' => 'حفلة حديقة'],
    ]);
    $inactive = ServiceTheme::factory()->inactive()->create([
        'code' => 'qa-inactive-theme',
        'name' => ['en' => 'Old Theme', 'ar' => 'طابع قديم'],
    ]);

    $page = match ($type) {
        ProductType::Rental => EditRentalService::class,
        ProductType::Sale => EditSaleService::class,
        ProductType::Digital => EditDigitalService::class,
    };
    $themeLabel = $active->getTranslation('name', $locale);

    Livewire::test($page, ['record' => $service->public_id])
        ->assertFormFieldExists('themes', fn (Select $field): bool => $field->isMultiple()
            && $field->getSearchResults('') === [$active->id => $themeLabel.' ('.$active->code.')'])
        ->fillForm(['themes' => [$active->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($service->fresh()->themes()->pluck('service_themes.id')->all())->toBe([$active->id]);

    $this->getJson('/api/v1/customer/services/'.$service->public_id, ['Accept-Language' => $locale])
        ->assertOk()
        ->assertJsonPath('data.themes.0.public_id', $active->public_id)
        ->assertJsonPath('data.themes.0.code', $active->code)
        ->assertJsonPath('data.themes.0.name', $themeLabel);

    $inactive->update(['is_active' => true]);
    $service->themes()->attach($inactive->id);
    $inactive->update(['is_active' => false]);

    $this->getJson('/api/v1/customer/services/'.$service->public_id, ['Accept-Language' => $locale])
        ->assertOk()
        ->assertJsonCount(1, 'data.themes')
        ->assertJsonMissing(['public_id' => $inactive->public_id]);
})->with(ProductType::cases())->with(['en', 'ar']);

it('rejects tampered theme IDs unless the active theme is assigned to that service', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any', 'view', 'update'] as $operation) {
        $admin->givePermissionTo(Permission::findOrCreate($operation.'_rental::service', 'web'));
    }
    $this->actingAs($admin);
    $service = Service::factory()->rental()->published()->create();
    $inactive = ServiceTheme::factory()->inactive()->create();
    $activeButUnassigned = ServiceTheme::factory()->active()->create();

    Livewire::test(EditRentalService::class, ['record' => $service->public_id])
        ->fillForm(['themes' => [$inactive->id, $activeButUnassigned->id]])
        ->call('save')
        ->assertHasFormErrors(['themes']);

    expect($service->fresh()->themes()->exists())->toBeFalse();
});

it('shows assigned active themes in the booking wizard and snapshots the selected theme in the draft', function (string $locale): void {
    app()->setLocale($locale);

    $customer = Customer::factory()->asCustomer()->create();
    $service = Service::factory()->sale()->published()->create();
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    $theme = ServiceTheme::factory()->active()->create([
        'name' => ['en' => 'Paper Garden', 'ar' => 'حديقة ورقية'],
    ]);
    $hiddenTheme = ServiceTheme::factory()->inactive()->create([
        'name' => ['en' => 'Hidden Theme', 'ar' => 'طابع مخفي'],
    ]);
    $service->themes()->attach([$theme->id, $hiddenTheme->id]);
    $occasion = Occasion::factory()->create(['is_active' => true]);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    $this->actingAs($customer)
        ->get('/'.$locale.'/wizard?service='.$service->public_id)
        ->assertOk()
        ->assertSee($theme->getTranslation('name', $locale), false)
        ->assertDontSee($hiddenTheme->getTranslation('name', $locale), false);

    $payload = [
        'service_id' => $service->public_id,
        'theme_public_id' => $theme->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
        'event_ends_at' => now()->addDays(2)->startOfHour()->addHours(3)->toDateTimeString(),
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '21 Theme Street',
            'recipient_name' => 'Theme Customer',
            'recipient_phone_e164' => '+201001234567',
        ],
        'quantity' => 1,
        'timezone' => 'Africa/Cairo',
    ];

    $this->post('/'.$locale.'/cart/setup', $payload)->assertRedirect('/'.$locale.'/cart');

    expect(Booking::query()->where('customer_id', $customer->id)->sole()->theme)->toBe([
        'public_id' => $theme->public_id,
        'code' => $theme->code,
        'name' => ['en' => 'Paper Garden', 'ar' => 'حديقة ورقية'],
    ]);

    $theme->update(['is_active' => false]);
    $theme->services()->detach($service->id);

    expect(Booking::query()->where('customer_id', $customer->id)->sole()->theme['name'])
        ->toBe(['en' => 'Paper Garden', 'ar' => 'حديقة ورقية']);
})->with(['en', 'ar']);

it('rejects an inactive or unassigned theme at booking setup without creating a draft', function (string $themeState): void {
    $customer = Customer::factory()->asCustomer()->create();
    $service = Service::factory()->sale()->published()->create();
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    $theme = ServiceTheme::factory()->{$themeState}()->create();
    $occasion = Occasion::factory()->create(['is_active' => true]);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    $this->actingAs($customer)
        ->from('/en/wizard?service='.$service->public_id)
        ->post('/en/cart/setup', [
            'service_id' => $service->public_id,
            'theme_public_id' => $theme->public_id,
            'occasion' => $occasion->code,
            'event_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
            'address' => [
                'city_id' => $city->public_id,
                'address_line' => '21 Theme Street',
                'recipient_name' => 'Theme Customer',
                'recipient_phone_e164' => '+201001234567',
            ],
            'timezone' => 'Africa/Cairo',
        ])
        ->assertRedirect('/en/wizard?service='.$service->public_id)
        ->assertSessionHasErrors('theme_public_id');

    expect(Booking::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
})->with(['active', 'inactive']);
