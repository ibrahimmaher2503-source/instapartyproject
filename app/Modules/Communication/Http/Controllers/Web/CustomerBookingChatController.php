<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Web;

use App\Modules\Communication\Domain\Models\ChatThread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CustomerBookingChatController
{
    public function __invoke(Request $request, string $threadPublicId): View
    {
        $thread = ChatThread::query()
            ->where('public_id', $threadPublicId)
            ->where('customer_id', $request->user()->id)
            ->with('booking')
            ->firstOrFail();
        abort_if($thread->booking === null, 404);

        return view('communication::storefront.account.booking-chat', [
            'bookingPublicId' => (string) $thread->booking->public_id,
            'threadPublicId' => $thread->public_id,
            'bookingReference' => $thread->booking->reference_no,
            'locale' => app()->getLocale(),
        ]);
    }
}
