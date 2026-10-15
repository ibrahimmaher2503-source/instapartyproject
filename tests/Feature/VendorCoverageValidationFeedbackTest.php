<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorCoverageAreasPage;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('clears only the corrected city error before resubmitting coverage', function (string $locale): void {
    app()->setLocale($locale);
    $vendor = VendorProfile::factory()->approved()->create();
    $city = City::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendor->user, 'vendor');

    $page = Livewire::test(VendorCoverageAreasPage::class)->mountAction('addArea')
        ->set('mountedActionsData.0.delivery_fee', -1)
        ->callMountedAction()->assertHasActionErrors(['city_id' => 'required', 'delivery_fee' => 'min']);
    $feeError = $page->instance()->getErrorBag()->first('mountedActionsData.0.delivery_fee');
    $page->set('mountedActionsData.0.city_id', $city->id)
        ->assertHasNoActionErrors(['city_id'])
        ->assertHasActionErrors(['delivery_fee' => $feeError]);
    expect($vendor->coverageAreas()->count())->toBe(0);

    $page->set('mountedActionsData.0.city_id', null)->assertHasActionErrors(['city_id' => 'required']);
    $page->set('mountedActionsData.0.city_id', $city->id)->set('mountedActionsData.0.delivery_fee', 0)
        ->callMountedAction()->assertHasNoActionErrors()->assertNotified();
    expect($vendor->coverageAreas()->sole()->city_id)->toBe($city->id);
})->with(['en', 'ar']);

it('keeps the coverage row order after editing an existing area', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $first = VendorCoverageArea::factory()->create(['vendor_profile_id' => $vendor->id]);
    VendorCoverageArea::factory()->create(['vendor_profile_id' => $vendor->id]);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendor->user, 'vendor');
    $page = Livewire::test(VendorCoverageAreasPage::class);
    $order = $page->instance()->getTableRecords()->pluck('city_id')->all();
    $page->callTableAction('edit', $first, data: ['delivery_fee' => 0, 'min_order' => 0])
        ->assertHasNoTableActionErrors()->assertNotified();
    expect(Livewire::test(VendorCoverageAreasPage::class)->instance()->getTableRecords()->pluck('city_id')->all())->toBe($order);
    expect($vendor->coverageAreas()->where('city_id', $first->city_id)->sole()->delivery_fee_minor)->toBe(0);
});
