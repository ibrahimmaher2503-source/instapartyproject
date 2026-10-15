<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Web;

use App\Modules\Booking\Domain\Contracts\CustomerBookingChatReader;
use App\Modules\Communication\Application\Actions\ListCustomerConversationInboxAction;
use App\Modules\Communication\Application\Services\ProviderSettingsResolver;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CustomerConversationInboxController
{
    public function __invoke(
        Request $request,
        ListCustomerConversationInboxAction $inbox,
        CustomerBookingChatReader $bookings,
        ProviderSettingsResolver $settings,
    ): View {
        /** @var User $customer */
        $customer = $request->user();
        $data = $inbox->execute($customer, $bookings);
        $firebase = $settings->firebase();
        $bookingChatConfigured = (bool) $firebase['enabled']
            && filled($firebase['project_id'])
            && is_array($firebase['credentials'])
            && $firebase['credentials'] !== [];

        return view('communication::storefront.account.chats', $data + [
            'bookingChatConfigured' => $bookingChatConfigured,
            'locale' => app()->getLocale(),
        ]);
    }
}
