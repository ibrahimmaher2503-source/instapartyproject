<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Communication\Filament\Resources\AdminInboxResource;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentAttempt;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Support\Domain\Models\SupportTicket;
use App\Modules\TrustSafety\Domain\Models\Report;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

dataset('operational reference lists', [
    ['Booking', 'BookingResource', 'booking'],
    ['Booking', 'BookingsMonitorResource', 'booking'],
    ['Booking', 'AdminBookingInterventionResource', 'booking'],
    ['Booking', 'BookingModificationResource', 'modification'],
    ['Payments', 'PaymentResource', 'payment'],
    ['Payments', 'RefundResource', 'refund'],
    ['Payments', 'PaymentAttemptResource', 'attempt'],
    ['Settlement', 'WithdrawalResource', 'withdrawal'],
    ['Settlement', 'WithdrawalsQueueResource', 'withdrawal'],
    ['Settlement', 'CommissionResource', 'commission'],
    ['Settlement', 'WalletLedgerViewerResource', 'wallet'],
    ['Communication', 'NotificationPreferenceResource', 'preference'],
    ['Communication', 'NotificationDispatchResource', 'dispatch'],
    ['Loyalty', 'LoyaltyLedgerResource', 'loyalty'],
    ['Loyalty', 'LoyaltyRedemptionResource', 'redemption'],
    ['Support', 'SupportTicketResource', 'ticket'],
    ['TrustSafety', 'ReportResource', 'report'],
]);

function operationalSearchRecords(string $suffix): array
{
    $customer = User::factory()->asCustomer()->create([
        'name' => 'Customer '.$suffix,
        'email' => 'customer-'.$suffix.'@example.test',
        'phone_e164' => $suffix === 'match' ? '+201011112345' : '+201022226789',
    ]);
    $vendorUser = User::factory()->asVendor()->create([
        'email' => 'vendor-'.$suffix.'@example.test',
        'phone_e164' => $suffix === 'match' ? '+201033334567' : '+201044448901',
    ]);
    $vendor = VendorProfile::factory()->approved()->create([
        'user_id' => $vendorUser->id,
        'business_name' => ['en' => 'Lantern '.$suffix, 'ar' => $suffix === 'match' ? 'فانوس مميز' : 'حفلات أخرى'],
    ]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'reference_no' => 'IP-SEARCH-'.$suffix,
        'lifecycle_status' => 'vendor_review',
        'submitted_at' => now()->subDays(3),
    ]);
    $allocation = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'response_deadline' => now()->subHour(),
    ]);
    $item = BookingItem::factory()->create(['booking_vendor_id' => $allocation->id]);
    $payment = Payment::factory()->captured()->create(['booking_id' => $booking->id, 'user_id' => $customer->id]);

    return [
        'booking' => $booking,
        'modification' => BookingModification::factory()->create(['booking_vendor_id' => $allocation->id]),
        'payment' => $payment,
        'refund' => Refund::factory()->create(['booking_id' => $booking->id, 'payment_id' => $payment->id]),
        'attempt' => PaymentAttempt::factory()->create(['payment_id' => $payment->id]),
        'wallet' => Wallet::factory()->create(['owner_type' => VendorProfile::class, 'owner_id' => $vendor->id]),
        'dispatch' => NotificationDispatch::factory()->create(['user_id' => $customer->id, 'reference_type' => Booking::class, 'reference_id' => $booking->id]),
        'preference' => NotificationPreference::factory()->create(['user_id' => $customer->id]),
        'withdrawal' => Withdrawal::factory()->create(['vendor_profile_id' => $vendor->id, 'requested_by_user_id' => $vendorUser->id]),
        'commission' => Commission::factory()->create(['booking_item_id' => $item->id, 'payment_id' => $payment->id, 'vendor_profile_id' => $vendor->id, 'status' => 'calculated']),
        'loyalty' => LoyaltyLedgerEntry::factory()->create(['user_id' => $customer->id, 'vendor_profile_id' => $vendor->id]),
        'redemption' => LoyaltyRedemption::factory()->create(['user_id' => $customer->id, 'vendor_profile_id' => $vendor->id, 'booking_id' => $booking->id]),
        'ticket' => SupportTicket::create(['user_id' => $customer->id, 'email' => $customer->email, 'booking_id' => $booking->id, 'subject' => 'Help '.$suffix, 'body' => 'Need help']),
        'report' => Report::create(['reporter_id' => $customer->id, 'reportable_type' => VendorProfile::class, 'reportable_id' => $vendor->id, 'reason' => 'other', 'status' => 'open']),
        'customer' => $customer,
        'vendor' => $vendor,
        'vendorUser' => $vendorUser,
    ];
}

it('searches populated operational lists by contacts and public references without widening results', function (string $module, string $resourceName, string $recordKey): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_bookings::monitor', 'view_any_booking::modification', 'view_any_withdrawals::queue', 'view_any_wallet::ledger::viewer', 'view_any_notification::preference', 'view_any_notification::dispatch', 'booking.intervene.access', 'view_any_refund', 'view_any_payment::attempt', 'view_any_support::ticket', 'view_any_report', 'view_any_loyalty::ledger', 'view_any_loyalty::redemption', 'view_any_wallet', 'view_any_commission', 'view_any_withdrawal'] as $name) {
        $admin->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $matched = operationalSearchRecords('match');
    $other = operationalSearchRecords('other');
    $outsideReview = Booking::factory()->create(['customer_id' => $matched['customer']->id, 'lifecycle_status' => 'completed']);
    $resource = 'App\\Modules\\'.$module.'\\Filament\\Resources\\'.$resourceName;
    $page = $resource::getPages()['index']->getPage();
    $queries = in_array($recordKey, ['withdrawal', 'wallet'], true)
        ? [$matched['vendorUser']->email, $matched['vendorUser']->phone_e164, $matched['vendor']->public_id, 'فانوس']
        : [$matched['customer']->email, $matched['customer']->phone_e164, $matched['customer']->public_id];
    if (in_array($recordKey, ['booking', 'payment', 'refund', 'attempt', 'modification', 'commission', 'redemption', 'ticket', 'dispatch'], true)) {
        $queries[] = $matched['booking']->reference_no;
        $queries[] = $matched['booking']->public_id;
    }
    if (in_array($recordKey, ['booking', 'payment', 'refund', 'attempt', 'modification', 'commission', 'loyalty', 'redemption', 'report'], true)) {
        $queries[] = 'فانوس';
        $queries[] = $matched['vendor']->public_id;
    }
    if ($recordKey !== 'attempt') {
        $queries[] = $matched[$recordKey]->public_id;
    }
    $component = Livewire::test($page)->assertSuccessful();
    foreach ($queries as $query) {
        $component->searchTable($query);
        $this->assertContains($matched[$recordKey]->id, $component->instance()->getFilteredTableQuery()->pluck($matched[$recordKey]->qualifyColumn('id'))->all(), 'Missing search match: '.$resourceName.' / '.$query);
        $component->assertCanSeeTableRecords([$matched[$recordKey]])
            ->assertCanNotSeeTableRecords([$other[$recordKey]]);
        if (in_array($resourceName, ['BookingsMonitorResource', 'AdminBookingInterventionResource'], true)) {
            $component->assertCanNotSeeTableRecords([$outsideReview]);
        }
    }
    $component->searchTable('no-such-operational-reference')->assertCanNotSeeTableRecords([$matched[$recordKey], $other[$recordKey]]);
})->with('operational reference lists');

it('keeps a customer without admin permissions out of operational search pages', function (string $module, string $resourceName): void {
    $this->actingAs(User::factory()->asCustomer()->create());
    operationalSearchRecords('restricted');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $resource = 'App\\Modules\\'.$module.'\\Filament\\Resources\\'.$resourceName;
    Livewire::test($resource::getPages()['index']->getPage())->assertForbidden();
})->with('operational reference lists');

it('keeps bilingual public reference inbox search scoped to the current administrator', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_admin::inbox', 'web'));
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $owned = AdminInboxItem::factory()->create(['admin_id' => $admin->id, 'title' => ['en' => 'Owned alert', 'ar' => 'تنبيه خاص']]);
    $foreign = AdminInboxItem::factory()->create(['title' => ['en' => 'Owned alert', 'ar' => 'تنبيه خاص']]);
    $page = AdminInboxResource::getPages()['index']->getPage();
    $component = Livewire::test($page)->assertSuccessful();
    foreach ([$owned->public_id, 'تنبيه', 'Owned'] as $search) {
        $component->searchTable($search)->assertCanSeeTableRecords([$owned])->assertCanNotSeeTableRecords([$foreign]);
    }
    $component->searchTable($foreign->public_id)->assertCanNotSeeTableRecords([$owned, $foreign]);
});
