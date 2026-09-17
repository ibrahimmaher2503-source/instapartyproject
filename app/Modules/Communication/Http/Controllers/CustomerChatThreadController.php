<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Domain\Models\ChatThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lists the chat threads (one per vendor) for a customer's booking, so the
 * Next.js chat page can subscribe to each Firestore thread (FR-EXT-056-001).
 *
 * @group Chat
 */
class CustomerChatThreadController
{
    /**
     * @response 200 {"data":[{"vendor_public_id":"01J...","firestore_thread_id":"01J...","frozen":false}],"meta":{"self_user_id":42},"errors":null}
     */
    public function __invoke(Request $request, string $booking): JsonResponse
    {
        $user = $request->user();

        $bookingModel = Booking::query()
            ->where('public_id', $booking)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $threads = ChatThread::query()
            ->where('booking_id', $bookingModel->id)
            ->with('vendorProfile:id,public_id')
            ->get();

        return response()->json([
            'data' => $threads->map(fn (ChatThread $thread): array => [
                'vendor_public_id' => $thread->vendorProfile?->public_id,
                'firestore_thread_id' => $thread->firestore_thread_id,
                'frozen' => $thread->frozen_at !== null,
            ])->all(),
            'meta' => ['self_user_id' => $user->id],
            'errors' => null,
        ]);
    }
}
