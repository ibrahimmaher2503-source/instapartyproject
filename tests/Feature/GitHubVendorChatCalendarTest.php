<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorAvailabilityCalendarPage;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Vendor\Components\RestrictedChatPanel;
use App\Modules\Communication\Filament\Vendor\Pages\VendorChatConversationPage;
use App\Modules\Communication\Filament\Vendor\Pages\VendorChatPage;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

it('lists only owned booking threads and rejects another vendors conversation', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $other = VendorProfile::factory()->approved()->create();
    $makeThread = function ($profile, string $reference): ChatThread {
        $booking = Booking::factory()->create(['reference_no' => $reference]);
        BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $profile->id]);

        return ChatThread::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $profile->id, 'customer_id' => $booking->customer_id]);
    };
    $owned = $makeThread($vendor, 'CHAT-OWNED');
    $foreign = $makeThread($other, 'CHAT-FOREIGN');
    $this->actingAs($vendor->user, 'vendor');
    Livewire::test(VendorChatPage::class)->assertCanSeeTableRecords([$owned])->assertCanNotSeeTableRecords([$foreign]);
    $this->get(VendorChatConversationPage::getUrl(['thread' => $owned->public_id], panel: 'vendor'))->assertOk();
    $this->get(VendorChatConversationPage::getUrl(['thread' => $foreign->public_id], panel: 'vendor'))->assertNotFound();
});

it('reloads scoped chat context across Livewire updates and still enforces a subsequent freeze', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create();
    BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'sub_status' => 'pending']);
    $thread = ChatThread::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'customer_id' => $booking->customer_id]);
    $this->actingAs($vendor->user, 'vendor');
    $page = Livewire::test(RestrictedChatPanel::class, ['bookingPublicId' => $booking->public_id]);
    $page->set('body', 'Please prepare the birthday decorations')->call('sendMessage')->assertSet('body', '');
    expect($thread->messages()->count())->toBe(1);
    $moderator = User::factory()->asAdmin()->create(['name' => 'Chat Freeze Reviewer']);
    $thread->update(['frozen_at' => now(), 'frozen_by' => $moderator->id]);
    $page->set('body', 'A second message after freeze')->call('sendMessage')->assertSet('isBlocked', true)->assertSee('Chat Freeze Reviewer');
    expect($thread->messages()->count())->toBe(1);
});

it('renders an initially frozen owned conversation without exposing mutable private model state', function (string $locale): void {
    app()->setLocale($locale);
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create();
    BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'sub_status' => 'pending']);
    $moderator = User::factory()->asAdmin()->create(['name' => 'Frozen Conversation Reviewer']);
    ChatThread::factory()->frozen($moderator->id)->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'customer_id' => $booking->customer_id]);
    $this->actingAs($vendor->user, 'vendor');
    Livewire::test(RestrictedChatPanel::class, ['bookingPublicId' => $booking->public_id])
        ->assertSee(__('communication::chat.frozen_banner_title'))->assertSee('Frozen Conversation Reviewer');
})->with(['en', 'ar']);

it('rechecks revoked phone verification on the nested chat hydration before sending', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create();
    BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'sub_status' => 'pending']);
    $thread = ChatThread::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'customer_id' => $booking->customer_id]);
    $this->actingAs($vendor->user, 'vendor');
    $page = Livewire::test(RestrictedChatPanel::class, ['bookingPublicId' => $booking->public_id]);
    $vendor->user->update(['phone_verified_at' => null]);
    $this->actingAs($vendor->user->fresh(), 'vendor');
    $page->set('body', 'Message after verification revoked')->assertForbidden();
    expect($thread->messages()->count())->toBe(0);
});

it('rejects a chat component for a booking outside the current vendor allocation', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create();
    BookingVendor::factory()->create(['booking_id' => $booking->id]);
    $this->actingAs($vendor->user, 'vendor');
    Livewire::test(RestrictedChatPanel::class, ['bookingPublicId' => $booking->public_id])->assertNotFound();
});

it('shows current rental blocks and reservations in the vendor timezone and scopes service selection', function (string $locale): void {
    app()->setLocale($locale);
    $this->travelTo(Carbon::parse('2026-10-13 12:00:00', 'UTC'));
    $vendor = VendorProfile::factory()->approved()->create();
    $vendor->user->update(['timezone' => 'Africa/Cairo']);
    $service = Service::factory()->rental()->create(['vendor_profile_id' => $vendor->id]);
    $foreign = Service::factory()->rental()->create(['name' => ['en' => 'FOREIGN PRIVATE', 'ar' => 'FOREIGN PRIVATE']]);
    ServiceAvailabilityBlock::query()->create(['service_id' => $service->id, 'starts_at' => '2026-10-20 09:00:00', 'ends_at' => '2026-10-20 11:00:00']);
    ServiceInventoryReservation::factory()->confirmed()->create(['service_id' => $service->id, 'product_type' => 'rental', 'reserved_starts_at' => '2026-10-21 08:00:00', 'reserved_ends_at' => '2026-10-21 10:00:00']);
    ServiceInventoryReservation::factory()->expired()->create(['service_id' => $service->id, 'product_type' => 'rental', 'reserved_starts_at' => '2026-10-22 08:00:00', 'reserved_ends_at' => '2026-10-22 10:00:00']);
    $this->actingAs($vendor->user->fresh(), 'vendor');
    $page = Livewire::test(VendorAvailabilityCalendarPage::class)
        ->assertSee('Africa/Cairo')->assertSee('20/10 12:00')->assertSee('21/10 11:00')
        ->assertDontSee('22/10 11:00')->assertDontSee('FOREIGN PRIVATE');
    $page->call('nextMonth')->assertSet('month', '2026-11')->call('previousMonth')->assertSet('month', '2026-10');
    $page->set('servicePublicId', $foreign->public_id)->assertNotFound();
})->with(['en', 'ar']);

it('blocks operational chat and calendar access for an unapproved vendor', function (): void {
    $vendor = VendorProfile::factory()->pending()->create();
    $this->actingAs($vendor->user, 'vendor');
    expect(VendorChatPage::canAccess())->toBeFalse()->and(VendorAvailabilityCalendarPage::canAccess())->toBeFalse();
    Livewire::test(VendorChatPage::class)->assertForbidden();
    Livewire::test(VendorAvailabilityCalendarPage::class)->assertForbidden();
});

it('rechecks vendor verification when a dedicated operational page hydrates', function (string $pageClass): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create();
    BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id]);
    $thread = ChatThread::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'customer_id' => $booking->customer_id]);
    $this->actingAs($vendor->user, 'vendor');
    $page = Livewire::test($pageClass, $pageClass === VendorChatConversationPage::class ? ['thread' => $thread->public_id] : []);
    $vendor->user->update(['phone_verified_at' => null]);
    $this->actingAs($vendor->user->fresh(), 'vendor');
    $page->call('$refresh')->assertForbidden();
})->with([VendorChatPage::class, VendorChatConversationPage::class, VendorAvailabilityCalendarPage::class]);

it('rechecks the owned conversation allocation on hydration', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create();
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id]);
    $thread = ChatThread::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'customer_id' => $booking->customer_id]);
    $this->actingAs($vendor->user, 'vendor');
    $page = Livewire::test(VendorChatConversationPage::class, ['thread' => $thread->public_id]);
    $allocation->delete();
    $page->call('$refresh')->assertNotFound();
});
