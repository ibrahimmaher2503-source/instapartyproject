<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\Models\ChatThread;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisions one ChatThread per (booking, vendor) when a booking is confirmed,
 * plus the matching Firestore thread document carrying participant identities
 * (FR-EXT-056-002). Idempotent on (booking_id, vendor_profile_id).
 */
class CreateChatThreadOnBookingConfirmed implements ShouldQueue
{
    public function __construct(
        private readonly FirestoreChatGateway $gateway,
    ) {}

    public function handle(object $event): void
    {
        $booking = $event->booking;
        $booking->loadMissing(['customer', 'vendors.vendor.user']);

        $customerUserId = (int) $booking->customer_id;

        foreach ($booking->vendors as $bookingVendor) {
            $vendorProfile = $bookingVendor->vendor;
            $vendorUser = $vendorProfile?->user;

            if ($vendorProfile === null || $vendorUser === null) {
                continue;
            }

            $thread = ChatThread::query()->firstOrNew([
                'booking_id' => $booking->id,
                'vendor_profile_id' => $vendorProfile->id,
            ]);

            if ($thread->exists) {
                continue;
            }

            $thread->public_id = Str::ulid()->toBase32();
            $thread->firestore_thread_id = $thread->public_id;
            $thread->customer_id = $customerUserId;
            $thread->status = 'open';
            $thread->save();

            $gateway = $this->gateway;
            $firestoreThreadId = (string) $thread->firestore_thread_id;
            $bookingPublicId = (string) $booking->public_id;
            $vendorUserId = (string) $vendorUser->id;

            DB::afterCommit(static function () use ($gateway, $firestoreThreadId, $bookingPublicId, $customerUserId, $vendorUserId): void {
                $gateway->createThread(
                    $firestoreThreadId,
                    $bookingPublicId,
                    (string) $customerUserId,
                    $vendorUserId,
                );
            });
        }
    }
}
