<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Booking\Domain\Contracts\CustomerBookingChatReader;
use App\Modules\Booking\Domain\DTOs\CustomerBookingChatReference;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Domain\Models\VendorInquiry;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListCustomerConversationInboxAction
{
    /**
     * @return array{
     *   inquiries: LengthAwarePaginator,
     *   bookingChats: LengthAwarePaginator,
     *   bookingsById: array<int, CustomerBookingChatReference>
     * }
     */
    public function execute(User $customer, CustomerBookingChatReader $bookings): array
    {
        $inquiries = VendorInquiry::query()
            ->forCustomer($customer->id)
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate(15, ['*'], 'inquiry_page');

        $bookingChats = ChatThread::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('booking_id')
            ->with('vendorProfile')
            ->latest('id')
            ->paginate(20, ['*'], 'booking_chat_page');

        $bookingReferences = $bookings->findOwnedBookings(
            $customer->id,
            $bookingChats->getCollection()->pluck('booking_id')->map(static fn ($id): int => (int) $id)->all(),
        );

        return [
            'inquiries' => $inquiries,
            'bookingChats' => $bookingChats,
            'bookingsById' => $bookingReferences->all(),
        ];
    }
}
