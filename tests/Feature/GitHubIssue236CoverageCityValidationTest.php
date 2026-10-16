<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorCoverageAreasPage;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('clears stale city required validation error as soon as valid city is selected', function (string $locale): void {
    app()->setLocale($locale);
    $vendor = VendorProfile::factory()->approved()->create();
    $city = City::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendor->user, 'vendor');

    $page = Livewire::test(VendorCoverageAreasPage::class)
        ->mountAction('addArea')
        ->set('mountedActionsData.0.delivery_fee', -5)
        ->callMountedAction()
        ->assertHasActionErrors(['city_id' => 'required', 'delivery_fee' => 'min']);

    // Before selecting city, city_id error is present
    expect($page->instance()->getErrorBag()->has('mountedActionsData.0.city_id'))->toBeTrue();

    // Select valid city
    $page->set('mountedActionsData.0.city_id', $city->id)
        ->assertHasNoActionErrors(['city_id']);

    // Stale city error is cleared, delivery_fee error is preserved
    expect($page->instance()->getErrorBag()->has('mountedActionsData.0.city_id'))->toBeFalse()
        ->and($page->instance()->getErrorBag()->has('mountedActionsData.0.delivery_fee'))->toBeTrue();

    // Clearing city triggers required validation error again
    $page->set('mountedActionsData.0.city_id', null)
        ->assertHasActionErrors(['city_id' => 'required']);

    // Re-selecting valid city and fixing fee clears all errors and saves
    $page->set('mountedActionsData.0.city_id', $city->id)
        ->set('mountedActionsData.0.delivery_fee', 25)
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect($vendor->coverageAreas()->count())->toBe(1)
        ->and($vendor->coverageAreas()->first()->city_id)->toBe($city->id)
        ->and($vendor->coverageAreas()->first()->delivery_fee_minor)->toBe(2500);
})->with(['en', 'ar']);
