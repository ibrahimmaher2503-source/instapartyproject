<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Domain\Models\VendorInquiry;
use App\Modules\Discovery\Domain\Models\SavedSearch;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Illuminate\Support\Str;

it('lets a customer manage only their own saved addresses in English and Arabic', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $other = User::factory()->asCustomer()->create();
    $city = City::factory()->create();
    $ownedAddress = CustomerAddress::factory()->forUser($customer)->create([
        'city_id' => $city->id,
        'label' => 'Home',
        'address_line' => '12 Example Street',
        'recipient_name' => 'Customer One',
    ]);
    $otherAddress = CustomerAddress::factory()->forUser($other)->create(['city_id' => $city->id]);

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.addresses', ['locale' => $locale]))
        ->assertOk()
        ->assertSeeText('12 Example Street')
        ->assertDontSeeText($otherAddress->address_line);

    $this->post(route('storefront.account.addresses.store', ['locale' => $locale]), [
        'city_id' => $city->public_id,
        'label' => 'Work',
        'address_line' => '4 New Street',
        'recipient_name' => 'Customer One',
        'recipient_phone_e164' => '+201001234567',
        'is_default' => '1',
    ])->assertRedirect();

    $createdAddress = CustomerAddress::query()->where('user_id', $customer->id)->where('address_line', '4 New Street')->sole();
    expect($createdAddress->is_default)->toBeTrue()
        ->and($ownedAddress->fresh()->is_default)->toBeFalse();

    $this->patch(route('storefront.account.addresses.update', ['locale' => $locale, 'addressPublicId' => $ownedAddress->public_id]), [
        'city_id' => $city->public_id,
        'label' => 'Home updated',
        'address_line' => '12 Updated Street',
        'recipient_name' => 'Customer One',
        'recipient_phone_e164' => '+201001234567',
    ])->assertRedirect();

    expect($ownedAddress->fresh()->address_line)->toBe('12 Updated Street');

    $this->post(route('storefront.account.addresses.default', ['locale' => $locale, 'addressPublicId' => $ownedAddress->public_id]))
        ->assertRedirect();

    expect($ownedAddress->fresh()->is_default)->toBeTrue()
        ->and($createdAddress->fresh()->is_default)->toBeFalse();

    $this->delete(route('storefront.account.addresses.destroy', ['locale' => $locale, 'addressPublicId' => $otherAddress->public_id]))
        ->assertNotFound();
    $this->delete(route('storefront.account.addresses.destroy', ['locale' => $locale, 'addressPublicId' => $ownedAddress->public_id]))
        ->assertRedirect();

    expect($ownedAddress->fresh()->trashed())->toBeTrue()
        ->and($otherAddress->fresh())->not->toBeNull();
})->with(['en', 'ar']);

it('shows only owned service conversations and configured booking chat states in the customer inbox', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $other = User::factory()->asCustomer()->create();
    $ownService = Service::factory()->digital()->published()->create();
    $foreignService = Service::factory()->digital()->published()->create();
    $ownInquiry = VendorInquiry::query()->create([
        'public_id' => (string) Str::ulid(),
        'customer_id' => $customer->id,
        'vendor_profile_id' => $ownService->vendor_profile_id,
        'service_id' => $ownService->id,
        'customer_name' => $customer->name,
        'service_public_id' => $ownService->public_id,
        'service_name_en' => $ownService->getTranslation('name', 'en'),
        'service_name_ar' => $ownService->getTranslation('name', 'ar'),
        'vendor_name_en' => 'Owned vendor',
        'vendor_name_ar' => 'مورد خاص بي',
    ]);
    VendorInquiry::query()->create([
        'public_id' => (string) Str::ulid(),
        'customer_id' => $other->id,
        'vendor_profile_id' => $foreignService->vendor_profile_id,
        'service_id' => $foreignService->id,
        'customer_name' => $other->name,
        'service_public_id' => $foreignService->public_id,
        'service_name_en' => 'Foreign private conversation marker',
        'service_name_ar' => 'علامة محادثة خاصة أخرى',
        'vendor_name_en' => 'Foreign vendor',
        'vendor_name_ar' => 'مورد آخر',
    ]);
    $booking = Booking::factory()->confirmed()->create([
        'customer_id' => $customer->id,
        'reference_no' => 'QA-CHAT-OWNED',
    ]);
    $foreignBooking = Booking::factory()->confirmed()->create([
        'customer_id' => $other->id,
        'reference_no' => 'QA-CHAT-FOREIGN',
    ]);
    ChatThread::factory()->create([
        'customer_id' => $customer->id,
        'booking_id' => $booking->id,
        'status' => 'open',
        'firestore_thread_id' => 'private-firestore-thread-marker',
    ]);
    ChatThread::factory()->create([
        'customer_id' => $other->id,
        'booking_id' => $foreignBooking->id,
        'firestore_thread_id' => 'foreign-private-thread-marker',
    ]);
    config()->set('services.firebase.credentials', 'local-test-placeholder');
    config()->set('services.firebase.project_id', 'local-test-project');

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.chats', ['locale' => $locale]))
        ->assertOk()
        ->assertSeeText($ownInquiry->serviceNameFor($locale))
        ->assertSee(route('storefront.inquiries.show', ['locale' => $locale, 'inquiryPublicId' => $ownInquiry->public_id]), false)
        ->assertDontSeeText('Foreign private conversation marker')
        ->assertDontSeeText('علامة محادثة خاصة أخرى')
        ->assertSeeText('QA-CHAT-OWNED')
        ->assertDontSeeText('QA-CHAT-FOREIGN')
        ->assertDontSeeText('private-firestore-thread-marker')
        ->assertDontSeeText('foreign-private-thread-marker');
})->with(['en', 'ar']);

it('keeps prebooking service conversations available while explaining unconfigured booking chat', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    config()->set('services.firebase.credentials', null);
    config()->set('services.firebase.project_id', null);

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.chats', ['locale' => $locale]))
        ->assertOk()
        ->assertSeeText(__('communication::chat.account.inquiries_heading', [], $locale))
        ->assertSeeText(__('communication::chat.account.booking_chat_unavailable', [], $locale));
})->with(['en', 'ar']);

it('saves normalized supported search filters and restricts saved-search changes to their owner', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $other = User::factory()->asCustomer()->create();
    $foreignSearch = SavedSearch::factory()->create([
        'user_id' => $other->id,
        'label' => 'Other customer private search',
        'filters' => ['q' => 'private-marker'],
    ]);

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.saved-searches.index', ['locale' => $locale]))
        ->assertOk()
        ->assertDontSeeText('Other customer private search')
        ->assertDontSeeText('private-marker');

    $this->post(route('storefront.account.saved-searches.store', ['locale' => $locale]), [
        'label' => '  Cairo rental  ',
        'filters' => [
            'q' => '  chairs  ',
            'product_type' => 'rental',
            'price_min' => '100',
            'sort' => 'price_asc',
            'page' => '7',
            'unsupported_internal_field' => 'should not persist',
        ],
    ])->assertRedirect();

    $saved = SavedSearch::query()->where('user_id', $customer->id)->sole();
    expect($saved->label)->toBe('Cairo rental')
        ->and($saved->filters)->toBe([
            'q' => 'chairs',
            'product_type' => 'rental',
            'price_min' => 100,
            'sort' => 'price_asc',
        ]);

    $this->actingAs($other, 'web')
        ->delete(route('storefront.account.saved-searches.destroy', ['locale' => $locale, 'savedSearch' => $saved->id]))
        ->assertNotFound();

    $this->actingAs($customer, 'web')
        ->delete(route('storefront.account.saved-searches.destroy', ['locale' => $locale, 'savedSearch' => $saved->id]))
        ->assertRedirect();

    expect(SavedSearch::query()->whereKey($saved->id)->exists())->toBeFalse()
        ->and(SavedSearch::query()->whereKey($foreignSearch->id)->exists())->toBeTrue();
})->with(['en', 'ar']);

it('shows only the signed-in customer loyalty balances and append-only history', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $other = User::factory()->asCustomer()->create();
    $program = LoyaltyProgram::factory()->create();
    $ownedEntry = LoyaltyLedgerEntry::factory()->create([
        'user_id' => $customer->id,
        'vendor_profile_id' => $program->vendor_profile_id,
        'loyalty_program_id' => $program->id,
        'direction' => 'earn',
        'points' => 42,
        'balance_after' => 42,
        'reason' => ['en' => 'Completed booking reward', 'ar' => 'مكافأة إتمام الحجز'],
    ]);
    $otherEntry = LoyaltyLedgerEntry::factory()->create([
        'user_id' => $other->id,
        'reason' => ['en' => 'Other customer reward marker', 'ar' => 'علامة مكافأة عميل آخر'],
    ]);
    $entryCount = LoyaltyLedgerEntry::query()->count();

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.loyalty', ['locale' => $locale]))
        ->assertOk()
        ->assertSeeText('42')
        ->assertSeeText($ownedEntry->getTranslation('reason', $locale))
        ->assertDontSeeText($otherEntry->getTranslation('reason', $locale));

    expect(LoyaltyLedgerEntry::query()->count())->toBe($entryCount)
        ->and($ownedEntry->fresh()->balance_after)->toBe(42)
        ->and($otherEntry->fresh()->user_id)->toBe($other->id);
})->with(['en', 'ar']);
