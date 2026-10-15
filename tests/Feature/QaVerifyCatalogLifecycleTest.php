<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Application\Actions\CloneServiceAction;
use App\Modules\Catalog\Application\Actions\VendorArchiveServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Domain\States\ServiceStatus\ArchivedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('clones services of every type into private drafts with schema values details and media', function (ProductType $type): void {
    config(['catalog.digital_services_enabled' => true]);
    Storage::fake('public');

    $vendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType($type)->create(['vendor_profile_id' => $vendor->id]);
    $factory = match ($type) {
        ProductType::Rental => Service::factory()->rental(),
        ProductType::Sale => Service::factory()->sale(),
        ProductType::Digital => Service::factory()->digital(),
    };
    $service = $factory->published()->create([
        'vendor_profile_id' => $vendor->id,
        'name' => ['en' => 'QA Source Service', 'ar' => 'خدمة المصدر للاختبار'],
        'base_price_minor' => 25000,
        'category_field_values' => ['capacity' => 48],
    ]);

    match ($type) {
        ProductType::Rental => ServiceRentalDetail::factory()->create([
            'service_id' => $service->id,
            'default_rental_duration_hours' => 4,
            'length_m' => '4.50',
        ]),
        ProductType::Sale => ServiceSaleDetail::factory()->create([
            'service_id' => $service->id,
            'is_made_to_order' => true,
            'lead_time_hours' => 24,
            'stock_quantity' => 12,
        ]),
        ProductType::Digital => ServiceDigitalDetail::factory()->create([
            'service_id' => $service->id,
            'delivery_method' => 'email',
            'has_expiry' => true,
            'expiry_days_after_purchase' => 7,
        ]),
    };
    $sourceMedia = $service->addMedia(UploadedFile::fake()->image('source.png'))->toMediaCollection('gallery');

    $clone = app(CloneServiceAction::class)->execute($service, $vendor);
    $cloneMedia = $clone->getMedia('gallery');

    expect($clone->public_id)->not->toBe($service->public_id)
        ->and($clone->status)->toBeInstanceOf(DraftState::class)
        ->and($clone->vendor_profile_id)->toBe($service->vendor_profile_id)
        ->and($clone->category_id)->toBe($service->category_id)
        ->and($clone->product_type)->toBe($type)
        ->and($clone->getTranslation('name', 'en'))->toBe('QA Source Service (Copy)')
        ->and($clone->getTranslation('name', 'ar'))->toBe('خدمة المصدر للاختبار (نسخة)')
        ->and($clone->base_price_minor)->toBe(25000)
        ->and($clone->category_field_values)->toBe(['capacity' => 48])
        ->and($cloneMedia)->toHaveCount(1)
        ->and($cloneMedia->sole()->id)->not->toBe($sourceMedia->id)
        ->and($service->refresh()->status)->toBeInstanceOf(PublishedState::class);

    match ($type) {
        ProductType::Rental => expect($clone->rentalDetail->default_rental_duration_hours)->toBe(4)
            ->and($clone->rentalDetail->length_m)->toBe('4.50'),
        ProductType::Sale => expect($clone->saleDetail->lead_time_hours)->toBe(24)
            ->and($clone->saleDetail->stock_quantity)->toBe(12),
        ProductType::Digital => expect($clone->digitalDetail->delivery_method)->toBe('email')
            ->and($clone->digitalDetail->expiry_days_after_purchase)->toBe(7),
    };
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('archives a published service without erasing its historical booking snapshot', function (ProductType $type): void {
    config(['catalog.digital_services_enabled' => true]);

    $vendor = VendorProfile::factory()->approved()->create();
    $factory = match ($type) {
        ProductType::Rental => Service::factory()->rental(),
        ProductType::Sale => Service::factory()->sale(),
        ProductType::Digital => Service::factory()->digital(),
    };
    $service = $factory->published()->create([
        'vendor_profile_id' => $vendor->id,
        'name' => ['en' => 'QA Historical Service', 'ar' => 'خدمة تاريخية للاختبار'],
    ]);
    $bookingVendor = BookingVendor::factory()->create(['vendor_profile_id' => $vendor->id]);
    $item = BookingItem::factory()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'service_id' => $service->id,
        'product_type' => $type,
        'name_snapshot' => ['en' => 'Booked service name', 'ar' => 'اسم الخدمة عند الحجز'],
        'type_snapshot' => ['category_field_values' => ['capacity' => 30]],
    ]);

    $this->get('/en/services/'.$service->public_id)->assertOk();
    $this->actingAs($vendor->user, 'vendor');
    app(VendorArchiveServiceAction::class)->execute($service, $vendor);

    expect($service->refresh()->status)->toBeInstanceOf(ArchivedState::class)
        ->and($item->refresh()->name_snapshot)->toBe(['en' => 'Booked service name', 'ar' => 'اسم الخدمة عند الحجز'])
        ->and($item->type_snapshot)->toBe(['category_field_values' => ['capacity' => 30]])
        ->and($item->service->public_id)->toBe($service->public_id);
    $this->get('/en/services/'.$service->public_id)->assertNotFound();

    expect(fn () => app(VendorArchiveServiceAction::class)->execute($service, $vendor))
        ->toThrow(ValidationException::class)
        ->and($service->refresh()->status)->toBeInstanceOf(ArchivedState::class);
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);
