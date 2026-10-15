<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CustomerDecideAlternativeVendorProposalAction;
use App\Modules\Booking\Application\Actions\SuggestAlternativeVendorsAction;
use App\Modules\Booking\Application\DTOs\CustomerAlternativeVendorProposalDecisionDTO;
use App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO;
use App\Modules\Booking\Application\Listeners\DispatchAlternativeVendorProposalNotifications;
use App\Modules\Booking\Database\Factories\BookingAddressFactory;
use App\Modules\Booking\Database\Factories\BookingFactory;
use App\Modules\Booking\Domain\Enums\CustomerConsentStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\AlternativeVendorProposalCreated;
use App\Modules\Booking\Domain\Events\AlternativeVendorProposalDecided;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages\ListBookingInterventions;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingsPage;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Communication\Database\Seeders\AlternativeVendorInAppNotificationTemplateSeeder;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function financeConsentSaleService(VendorProfile $vendor, int $categoryId, int $stock = 20): Service
{
    $vendor->approvedTypes()->firstOrCreate([
        'product_type' => ProductType::Sale->value,
        'revoked_at' => null,
    ], ['approved_at' => now()]);

    $service = Service::factory()->sale()->published()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $categoryId,
        'base_price_minor' => 1200,
        'base_price_currency' => 'EGP',
    ]);
    ServiceSaleDetail::factory()->create([
        'service_id' => $service->id,
        'stock_quantity' => $stock,
        'customization_fields' => [],
    ]);

    return $service;
}

function financeConsentFixture(): array
{
    $category = Category::factory()->create(['allowed_product_types' => [ProductType::Sale->value]]);
    $sourceVendorProfile = VendorProfile::factory()->approved()->create();
    $targetVendorProfile = VendorProfile::factory()->approved()->create();
    $sourceService = financeConsentSaleService($sourceVendorProfile, $category->id);
    $targetService = financeConsentSaleService($targetVendorProfile, $category->id);
    $booking = BookingFactory::new()->create([
        'lifecycle_status' => VendorReviewState::class,
        'subtotal_minor' => 2500,
        'delivery_total_minor' => 500,
        'total_minor' => 3000,
    ]);
    $category->occasions()->syncWithoutDetaching([$booking->occasion_id]);
    $address = BookingAddressFactory::new()->create(['booking_id' => $booking->id]);
    DB::table('vendor_coverage_areas')->insert([
        'vendor_profile_id' => $targetVendorProfile->id,
        'city_id' => $address->city_id,
        'delivery_fee_minor' => 500,
        'delivery_fee_currency' => 'EGP',
        'min_order_minor' => 0,
        'min_order_currency' => 'EGP',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $deadline = CarbonImmutable::now('UTC')->addHours(3)->startOfSecond();
    $bookingVendor = BookingVendor::query()->create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $booking->id,
        'vendor_profile_id' => $sourceVendorProfile->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => $deadline,
        'subtotal_minor' => 2400,
        'subtotal_currency' => 'EGP',
        'delivery_fee_minor' => 500,
        'delivery_fee_currency' => 'EGP',
        'commission_minor' => 240,
        'commission_currency' => 'EGP',
        'vendor_payout_minor' => 2660,
        'vendor_payout_currency' => 'EGP',
    ]);
    $item = BookingItem::query()->create([
        'public_id' => (string) Str::ulid(),
        'booking_vendor_id' => $bookingVendor->id,
        'service_id' => $sourceService->id,
        'product_type' => ProductType::Sale,
        'name_snapshot' => ['en' => 'Original Tableware', 'ar' => 'أدوات المائدة الأصلية'],
        'unit_price_minor' => 1200,
        'unit_price_currency' => 'EGP',
        'line_total_minor' => 2400,
        'line_total_currency' => 'EGP',
        'commission_minor' => 240,
        'commission_currency' => 'EGP',
        'quantity' => 2,
        'item_status' => 'pending',
        'commission_bps' => 1000,
        'type_snapshot' => ['service_public_id' => $sourceService->public_id, 'product_type' => 'sale'],
        'vat_rate_bps' => 0,
        'vat_amount_minor' => 0,
        'vat_amount_currency' => 'EGP',
    ]);
    DB::table('service_inventory_reservations')->insert([
        'public_id' => (string) Str::ulid(),
        'service_id' => $sourceService->id,
        'user_id' => $booking->customer_id,
        'product_type' => ProductType::Sale->value,
        'hold_type' => 'cart',
        'status' => 'held',
        'quantity' => 2,
        'expires_at' => now()->addMinutes(10),
        'booking_item_id' => $item->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('commission_rates')->insert([
        'public_id' => (string) Str::ulid(),
        'category_id' => $category->id,
        'product_type' => ProductType::Sale->value,
        'commission_bps' => 1250,
        'effective_from' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('booking.intervene.access', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate('booking.intervene.suggest_alternative_vendors', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return compact('booking', 'bookingVendor', 'item', 'sourceService', 'targetService', 'sourceVendorProfile', 'targetVendorProfile', 'deadline', 'admin');
}

it('preserves the original pending allocation until customer consent then transfers its full quote and inventory atomically', function (): void {
    Queue::fake();
    app(AlternativeVendorInAppNotificationTemplateSeeder::class)->run();
    $fixture = financeConsentFixture();
    $booking = $fixture['booking'];
    $sourceAllocation = $fixture['bookingVendor'];

    $booking->update(['submitted_at' => now()->subHours(50)]);
    $finder = Mockery::mock(AlternativeVendorFinder::class);
    $finder->shouldReceive('findCandidates')->andReturn(collect([$fixture['targetVendorProfile']]));
    app()->instance(AlternativeVendorFinder::class, $finder);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    app()->setLocale('en');
    $this->actingAs($fixture['admin'], 'web');

    $adminPage = Livewire::test(ListBookingInterventions::class)
        ->assertCanSeeTableRecords([$booking])
        ->assertTableActionVisible('suggestAlternativeVendors', $booking)
        ->mountTableAction('suggestAlternativeVendors', $booking)
        ->assertSet('mountedTableActions', ['suggestAlternativeVendors'])
        ->setTableActionData([
            'idempotency_key' => (string) Str::uuid(),
            'booking_vendor_public_id' => $sourceAllocation->public_id,
            'vendor_profile_id' => $fixture['targetVendorProfile']->id,
            'reason' => 'The assigned vendor cannot fulfill the booked stock quantity.',
        ])
        ->assertTableActionDataSet([
            'booking_vendor_public_id' => $sourceAllocation->public_id,
            'vendor_profile_id' => $fixture['targetVendorProfile']->id,
            'reason' => 'The assigned vendor cannot fulfill the booked stock quantity.',
        ])
        ->assertHasNoTableActionErrors();
    $adminPage->call('callMountedTableAction')->assertHasNoTableActionErrors();

    $proposal = BookingAdminIntervention::query()->where('booking_id', $booking->id)->sole();

    $offer = $proposal->after_state['offer'];
    expect($proposal->customer_consent_status)->toBe(CustomerConsentStatus::Pending)
        ->and($proposal->proposed_vendor_id)->toBeNull()
        ->and($proposal->consent_expires_at?->toISOString())->toBe($fixture['deadline']->toISOString())
        ->and($offer['items'][0]['replacement_service']['public_id'])->toBe($fixture['targetService']->public_id)
        ->and($offer['items'][0]['quantity'])->toBe(2)
        ->and($offer['items'][0]['line_total_minor'])->toBe(2400)
        ->and($sourceAllocation->fresh()->vendor_profile_id)->toBe($fixture['sourceVendorProfile']->id)
        ->and($sourceAllocation->fresh()->response_deadline?->toISOString())->toBe($fixture['deadline']->toISOString())
        ->and($fixture['item']->fresh()->service_id)->toBe($fixture['sourceService']->id)
        ->and(DB::table('service_inventory_reservations')->where('booking_item_id', $fixture['item']->id)->value('service_id'))->toBe($fixture['sourceService']->id)
        ->and($booking->fresh()->total_minor)->toBe(3000);

    $this->actingAs(User::query()->findOrFail($booking->customer_id), 'web')
        ->post(route('storefront.account.bookings.vendor-proposals.decide', [
            'locale' => 'en',
            'bookingPublicId' => $booking->public_id,
            'interventionPublicId' => $proposal->public_id,
        ]), ['decision' => 'accepted', 'offer_hash' => $offer['offer_hash']])
        ->assertRedirect()
        ->assertSessionHas('vendor_proposal_decision_saved', true);

    $sourceAllocation->refresh();
    $fixture['item']->refresh();
    expect($proposal->fresh()->customer_consent_status)->toBe(CustomerConsentStatus::Accepted)
        ->and($proposal->fresh()->proposed_vendor_id)->toBe($fixture['targetVendorProfile']->id)
        ->and($sourceAllocation->vendor_profile_id)->toBe($fixture['targetVendorProfile']->id)
        ->and($sourceAllocation->sub_status)->toBe(VendorSubStatus::Pending)
        ->and($sourceAllocation->response_deadline?->toISOString())->toBe($fixture['deadline']->toISOString())
        ->and($fixture['item']->service_id)->toBe($fixture['targetService']->id)
        ->and($fixture['item']->line_total_minor)->toBe(2400)
        ->and($fixture['item']->commission_bps)->toBe(1250)
        ->and($fixture['item']->commission_minor)->toBe(300)
        ->and($sourceAllocation->subtotal_minor)->toBe(2400)
        ->and($sourceAllocation->delivery_fee_minor)->toBe(500)
        ->and($sourceAllocation->commission_minor)->toBe(300)
        ->and($sourceAllocation->vendor_payout_minor)->toBe(2600)
        ->and(DB::table('service_inventory_reservations')->where('booking_item_id', $fixture['item']->id)->value('service_id'))->toBe($fixture['targetService']->id)
        ->and($booking->fresh()->total_minor)->toBe(3000)
        ->and(DB::table('commissions')->count())->toBe(0)
        ->and(DB::table('wallet_ledger')->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'booking.vendor_proposal_decision')->count())->toBe(1);
});

it('records customer rejection without changing the pending vendor, quote, deadline, or reservation', function (): void {
    $fixture = financeConsentFixture();
    $proposal = app(SuggestAlternativeVendorsAction::class)->execute($fixture['booking'], new SuggestedAlternativeVendorsDTO(
        bookingId: $fixture['booking']->id,
        adminId: $fixture['admin']->id,
        bookingVendorPublicId: $fixture['bookingVendor']->public_id,
        vendorProfileId: $fixture['targetVendorProfile']->id,
        reason: 'The assigned vendor cannot fulfill the booked stock quantity.',
    ));
    $offer = $proposal->after_state['offer'];

    app(CustomerDecideAlternativeVendorProposalAction::class)->execute(
        new CustomerAlternativeVendorProposalDecisionDTO(
            bookingPublicId: $fixture['booking']->public_id,
            interventionPublicId: $proposal->public_id,
            customerId: $fixture['booking']->customer_id,
            decision: 'rejected',
            offerHash: $offer['offer_hash'],
        ),
    );

    expect($proposal->fresh()->customer_consent_status)->toBe(CustomerConsentStatus::Rejected)
        ->and($proposal->fresh()->proposed_vendor_id)->toBeNull()
        ->and($fixture['bookingVendor']->fresh()->vendor_profile_id)->toBe($fixture['sourceVendorProfile']->id)
        ->and($fixture['bookingVendor']->fresh()->response_deadline?->toISOString())->toBe($fixture['deadline']->toISOString())
        ->and($fixture['item']->fresh()->service_id)->toBe($fixture['sourceService']->id)
        ->and(DB::table('service_inventory_reservations')->where('booking_item_id', $fixture['item']->id)->value('service_id'))->toBe($fixture['sourceService']->id)
        ->and($fixture['booking']->fresh()->total_minor)->toBe(3000)
        ->and(DB::table('audit_logs')->where('action', 'booking.vendor_proposal_decision')->count())->toBe(1);
});

it('renders a pending proposal and accepts it through the authenticated customer web flow exactly once', function (): void {
    $fixture = financeConsentFixture();
    $proposal = app(SuggestAlternativeVendorsAction::class)->execute($fixture['booking'], new SuggestedAlternativeVendorsDTO(
        bookingId: $fixture['booking']->id,
        adminId: $fixture['admin']->id,
        bookingVendorPublicId: $fixture['bookingVendor']->public_id,
        vendorProfileId: $fixture['targetVendorProfile']->id,
        reason: 'The assigned vendor cannot fulfill the booked stock quantity.',
    ));
    $offer = $proposal->after_state['offer'];
    $customer = User::query()->findOrFail($fixture['booking']->customer_id);

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.bookings.show', ['locale' => 'en', 'publicId' => $fixture['booking']->public_id]))
        ->assertOk()
        ->assertSee(__('booking::booking.intervention.proposal_title'))
        ->assertSee(__('booking::booking.intervention.proposal_accept'))
        ->assertSee(data_get($offer, 'candidate_vendor.business_name.'.app()->getLocale()));

    $url = route('storefront.account.bookings.vendor-proposals.decide', [
        'locale' => 'en',
        'bookingPublicId' => $fixture['booking']->public_id,
        'interventionPublicId' => $proposal->public_id,
    ]);
    $payload = ['decision' => 'accepted', 'offer_hash' => $offer['offer_hash'], 'reason' => 'Approved.'];

    $this->post($url, $payload)->assertRedirect()->assertSessionHas('vendor_proposal_decision_saved', true);
    $this->get(route('storefront.account.bookings.show', ['locale' => 'en', 'publicId' => $fixture['booking']->public_id]))
        ->assertOk()
        ->assertSee(__('booking::booking.intervention.success.proposal_decision_saved'));
    expect($proposal->fresh()->customer_consent_status)->toBe(CustomerConsentStatus::Accepted)
        ->and($proposal->fresh()->after_state['customer_decision']['reason'])->toBe('Approved.');

    $this->post($url, $payload)->assertStatus(409);
    expect(DB::table('audit_logs')->where('action', 'booking.vendor_proposal_decision')->count())->toBe(1);
});

it('does not disclose a customer proposal to guests or another customer', function (): void {
    $fixture = financeConsentFixture();
    $proposal = app(SuggestAlternativeVendorsAction::class)->execute($fixture['booking'], new SuggestedAlternativeVendorsDTO(
        bookingId: $fixture['booking']->id,
        adminId: $fixture['admin']->id,
        bookingVendorPublicId: $fixture['bookingVendor']->public_id,
        vendorProfileId: $fixture['targetVendorProfile']->id,
        reason: 'The assigned vendor cannot fulfill the booked stock quantity.',
    ));
    $url = route('storefront.account.bookings.vendor-proposals.decide', [
        'locale' => 'en',
        'bookingPublicId' => $fixture['booking']->public_id,
        'interventionPublicId' => $proposal->public_id,
    ]);
    $payload = ['decision' => 'rejected', 'offer_hash' => $proposal->after_state['offer']['offer_hash']];

    $this->post($url, $payload)->assertRedirect();

    $otherCustomer = User::factory()->phoneVerified()->asCustomer()->create();
    $this->actingAs($otherCustomer, 'web')->post($url, $payload)->assertNotFound();
    expect($proposal->fresh()->customer_consent_status)->toBe(CustomerConsentStatus::Pending);
});

it('dispatches one in-app notice per recipient for each proposal lifecycle event', function (): void {
    Queue::fake();
    app(AlternativeVendorInAppNotificationTemplateSeeder::class)->run();
    $fixture = financeConsentFixture();
    $proposal = app(SuggestAlternativeVendorsAction::class)->execute($fixture['booking'], new SuggestedAlternativeVendorsDTO(
        bookingId: $fixture['booking']->id,
        adminId: $fixture['admin']->id,
        bookingVendorPublicId: $fixture['bookingVendor']->public_id,
        vendorProfileId: $fixture['targetVendorProfile']->id,
        reason: 'The assigned vendor cannot fulfill the booked stock quantity.',
    ));
    $listener = app(DispatchAlternativeVendorProposalNotifications::class);
    $created = new AlternativeVendorProposalCreated($fixture['booking']->id, $proposal->id);
    $listener->created($created);
    $listener->created($created);

    $proposed = NotificationDispatch::query()
        ->where('channel', NotificationChannel::InApp)
        ->where('reference_id', $proposal->id)
        ->whereHas('template', fn ($query) => $query->where('event_key', 'booking.alternative_vendor.proposed'))
        ->get();
    expect($proposed)->toHaveCount(1)
        ->and($proposed->first()->user_id)->toBe($fixture['booking']->customer_id)
        ->and($proposed->first()->context['action_url'])->toContain($fixture['booking']->public_id)
        ->and($proposed->first()->context['event_identity'])->toBe($proposal->public_id.':proposed:customer');

    $decided = new AlternativeVendorProposalDecided(
        $fixture['booking']->id,
        $proposal->id,
        'accepted',
        $fixture['sourceVendorProfile']->id,
        $fixture['targetVendorProfile']->id,
    );
    $listener->decided($decided);
    $listener->decided($decided);
    $decisions = NotificationDispatch::query()
        ->where('channel', NotificationChannel::InApp)
        ->where('reference_id', $proposal->id)
        ->whereHas('template', fn ($query) => $query->where('event_key', 'booking.alternative_vendor.decided'))
        ->get();

    expect($decisions)->toHaveCount(3)
        ->and($decisions->pluck('user_id')->all())->toContain(
            $fixture['booking']->customer_id,
            $fixture['sourceVendorProfile']->user_id,
            $fixture['targetVendorProfile']->user_id,
        )
        ->and($decisions->filter(fn (NotificationDispatch $dispatch): bool => $dispatch->template?->audience === NotificationAudience::Customer))->toHaveCount(1)
        ->and($decisions->filter(fn (NotificationDispatch $dispatch): bool => $dispatch->template?->audience === NotificationAudience::Vendor))->toHaveCount(2)
        ->and($decisions->first(fn (NotificationDispatch $dispatch): bool => $dispatch->user_id === $fixture['targetVendorProfile']->user_id)?->context['action_url'])
        ->toBe(VendorBookingsPage::getUrl(panel: 'vendor'));
});
