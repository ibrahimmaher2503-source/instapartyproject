<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AddItemToBookingAction;
use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Database\Factories\ServiceSaleDetailFactory;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Settlement\Domain\Models\CommissionRate;
use Illuminate\Support\Facades\DB;

it('snapshots the resolved commission and allows unlimited sale stock', function (): void {
    $booking = Booking::factory()->draft()->create();
    $service = Service::factory()->sale()->published()->create([
        'base_price_minor' => 10_000,
        'base_price_currency' => 'EGP',
    ]);

    ServiceSaleDetailFactory::new()->create([
        'service_id' => $service->id,
        'stock_quantity' => null,
    ]);

    CommissionRate::factory()->create([
        'category_id' => $service->category_id,
        'product_type' => ProductType::Sale,
        'commission_bps' => 1_250,
        'effective_from' => now()->subDay(),
    ]);

    $item = app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
        bookingId: $booking->id,
        customerId: $booking->customer_id,
        serviceId: $service->id,
        quantity: 3,
        effectiveStartsAt: null,
        effectiveEndsAt: null,
        customizationData: null,
    ));

    expect($item->commission_bps)->toBe(1_250)
        ->and($item->line_total_minor)->toBe(30_000)
        ->and(DB::table('service_inventory_reservations')->where('booking_item_id', $item->id)->exists())->toBeTrue();
});
