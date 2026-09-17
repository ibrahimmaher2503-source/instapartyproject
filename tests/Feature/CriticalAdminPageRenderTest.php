<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CustomerConfirmModifiedBookingAction;
use App\Modules\Booking\Application\Actions\ExpireStaleBookingModificationsAction;
use App\Modules\Booking\Application\DTOs\CustomerModificationDecisionDTO;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

function criticalPagesAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();

    foreach ([
        'view_communication_provider_health',
        'view_any_sale::service',
        'create_sale::service',
        'update_sale::service',
        'view_sale::service',
        'view_any_rental::service',
        'view_any_digital::service',
        'view_any_bookings::monitor',
        'view_bookings::monitor',
        'view_any_booking::modification',
        'view_booking::modification',
        'create_occasion',
        'view_any_occasion',
        'view_occasion',
        'create_category',
        'view_any_category',
        'view_category',
        'create_loyalty::program',
        'view_any_loyalty::program',
        'view_loyalty::program',
    ] as $name) {
        $admin->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $admin;
}

it('renders sale provider booking and modification admin pages', function (): void {
    $this->actingAs(criticalPagesAdmin());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $booking = Booking::factory()->create();
    $modification = BookingModification::factory()->create();
    $sale = Service::factory()->create(['product_type' => ProductType::Sale]);
    Category::factory()->create(['name' => ['en' => 'Deferred Category Marker', 'ar' => 'علامة تصنيف مؤجل']]);

    $this->get('/admin/sale-services/create')->assertOk()->assertDontSee('Deferred Category Marker');
    $this->get('/admin/rental-services/pending-review')->assertOk();
    $this->get('/admin/sale-services/pending-review')->assertOk();
    $this->get('/admin/digital-services/pending-review')->assertOk();
    $this->get('/admin/sale-services/'.$sale->public_id)->assertOk();
    $this->get('/admin/sale-services/'.$sale->public_id.'/edit')->assertOk();
    $this->get('/admin/occasions/create')->assertOk();
    $this->get('/admin/categories/create')->assertOk();
    $this->get('/admin/loyalty-programs/create')->assertOk();
    $this->get('/admin/communication-provider-health-page')->assertOk();
    $this->get('/admin/bookings/'.$booking->public_id)->assertOk();
    $this->get('/admin/booking-modifications/'.$modification->public_id)
        ->assertOk()
        ->assertDontSee('Accept (Admin Proxy)')
        ->assertDontSee('Reject (Admin Proxy)');
});

it('rejects an expired customer modification decision under the row lock', function (): void {
    $modification = BookingModification::factory()->create([
        'status' => ModificationStatus::Pending,
        'expires_at' => now('UTC')->subSecond(),
    ]);
    $booking = $modification->bookingVendor->booking;

    $dto = new CustomerModificationDecisionDTO(
        bookingId: $booking->id,
        customerId: $booking->customer_id,
        modificationPublicId: $modification->public_id,
        decision: 'accepted',
        idempotencyKey: 'qa-expired-modification-decision',
    );

    try {
        app(CustomerConfirmModifiedBookingAction::class)->execute($dto);
        $this->fail('Expired modification decision was accepted.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(Response::HTTP_CONFLICT);
    }

    expect($modification->refresh()->status)->toBe(ModificationStatus::Pending);
});

it('denies critical admin detail routes to an unauthorized user', function (): void {
    $this->actingAs(User::factory()->create());
    $booking = Booking::factory()->create();
    $modification = BookingModification::factory()->create();
    $sale = Service::factory()->create(['product_type' => ProductType::Sale]);

    $this->get('/admin/sale-services/'.$sale->public_id)->assertForbidden();
    $this->get('/admin/bookings/'.$booking->public_id)->assertForbidden();
    $this->get('/admin/booking-modifications/'.$modification->public_id)->assertForbidden();
});

it('expires pending modifications at the UTC boundary exactly once', function (): void {
    $cutoff = CarbonImmutable::parse('2026-09-06 12:00:00', 'UTC');
    $boundary = BookingModification::factory()->create([
        'status' => ModificationStatus::Pending,
        'expires_at' => $cutoff,
    ]);
    $future = BookingModification::factory()->create([
        'status' => ModificationStatus::Pending,
        'expires_at' => $cutoff->addSecond(),
    ]);

    $action = app(ExpireStaleBookingModificationsAction::class);

    expect($action->execute($cutoff))->toBe(1)
        ->and($boundary->refresh()->status)->toBe(ModificationStatus::Expired)
        ->and($future->refresh()->status)->toBe(ModificationStatus::Pending)
        ->and($action->execute($cutoff))->toBe(0);

    $this->assertDatabaseCount('state_transitions', 1);
});
