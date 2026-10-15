<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Vendor\Pages;

use App\Modules\Communication\Application\Actions\SendVendorInquiryMessageAction;
use App\Modules\Communication\Domain\Models\VendorInquiry;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use App\Modules\Shared\Application\Services\VendorOperationalAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

class VendorInquiryConversationPage extends Page
{
    use RequiresApprovedVendor;

    protected static ?string $slug = 'vendor-inquiries/{inquiry}';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-inquiry-conversation';

    #[Locked]
    public string $inquiryPublicId = '';

    public string $body = '';

    public function mount(string $inquiry): void
    {
        $this->inquiryPublicId = $inquiry;
        $this->getInquiry();
    }

    public function hydrate(): void
    {
        $this->getInquiry();
    }

    public function getTitle(): string
    {
        return __('communication::communication.vendor_inquiries.conversation_title');
    }

    public function getInquiry(): VendorInquiry
    {
        abort_unless(VendorOperationalAccess::allowed(), 403);
        $vendorProfileId = (int) auth()->user()->vendorProfile->id;

        return VendorInquiry::query()
            ->forVendor($vendorProfileId)
            ->where('public_id', $this->inquiryPublicId)
            ->with('messages')
            ->firstOrFail();
    }

    public function sendReply(): void
    {
        $inquiry = $this->getInquiry();
        $this->validate(['body' => 'required|string|max:5000']);

        app(SendVendorInquiryMessageAction::class)->asVendor(
            $inquiry->public_id,
            (int) auth()->id(),
            (int) auth()->user()->vendorProfile->id,
            $this->body,
        );

        $this->body = '';
        Notification::make()
            ->title(__('communication::communication.vendor_inquiries.reply_sent'))
            ->success()
            ->send();
    }
}
