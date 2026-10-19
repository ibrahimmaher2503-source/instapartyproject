<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Vendor\Pages;

use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

class VendorChatConversationPage extends Page
{
    use RequiresApprovedVendor;

    protected static ?string $slug = 'chats/{thread}';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-chat-conversation';

    #[Locked]
    public string $bookingPublicId;

    public function mount(string $thread): void
    {
        $vendorId = auth()->user()->vendorProfile->id;
        $record = ChatThread::query()->where('public_id', $thread)->where('vendor_profile_id', $vendorId)
            ->whereHas('booking.vendors', fn ($query) => $query->where('vendor_profile_id', $vendorId))
            ->with('booking')->firstOrFail();
        $this->bookingPublicId = $record->booking->public_id;
    }

    public function getTitle(): string
    {
        return __('communication::chat.panel_title');
    }
}
