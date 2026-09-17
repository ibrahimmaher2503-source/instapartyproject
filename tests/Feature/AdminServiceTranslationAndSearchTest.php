<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages\EditDigitalService;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages\ListDigitalServices;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\EditRentalService;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\ListRentalServices;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\EditSaleService;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\ListSaleServices;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('keeps service translations separate and searches both locales for every product type', function (): void {
    $admin = User::factory()->superAdmin()->create();

    foreach (['rental', 'sale', 'digital'] as $type) {
        foreach (['view_any', 'view', 'update'] as $action) {
            $admin->givePermissionTo(Permission::findOrCreate("{$action}_{$type}::service", 'web'));
        }
    }

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $cases = [
        [Service::factory()->rental()->create(['name' => ['en' => 'Golden Rental Lantern', 'ar' => 'فانوس تأجير ذهبي']]), ServiceRentalDetail::class, ListRentalServices::class, EditRentalService::class, 'Rental Updated', 'تأجير محدث'],
        [Service::factory()->sale()->create(['name' => ['en' => 'Silver Sale Lantern', 'ar' => 'فانوس بيع فضي']]), ServiceSaleDetail::class, ListSaleServices::class, EditSaleService::class, 'Sale Updated', 'بيع محدث'],
        [Service::factory()->digital()->create(['name' => ['en' => 'Blue Digital Lantern', 'ar' => 'فانوس رقمي أزرق']]), ServiceDigitalDetail::class, ListDigitalServices::class, EditDigitalService::class, 'Digital Updated', 'رقمي محدث'],
    ];

    foreach ($cases as [$service, $detailClass, $listPage, $editPage, $english, $arabic]) {
        $detailClass::factory()->create(['service_id' => $service->id]);

        Livewire::test($listPage)->searchTable('Lantern')->assertCanSeeTableRecords([$service]);
        Livewire::test($listPage)->searchTable('فانوس')->assertCanSeeTableRecords([$service]);

        Livewire::test($editPage, ['record' => $service->getRouteKey()])
            ->fillForm(['name.en' => $english, 'name.ar' => $arabic])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($service->refresh()->getTranslations('name'))->toMatchArray(['en' => $english, 'ar' => $arabic]);
    }
});
