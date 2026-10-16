<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Vendor\Components;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Application\Actions\SendVendorChatMessageAction;
use App\Modules\Communication\Application\Actions\SendCustomerChatMessageAction;
use App\Modules\Communication\Application\DTOs\SendVendorChatMessageDTO;
use App\Modules\Communication\Application\Services\ChatPanelStateResolver;
use App\Modules\Communication\Domain\Enums\ChatPanelState;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\VendorOperationalAccess;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RestrictedChatPanel extends Component
{
    // -----------------------------------------------------------------
    // Public properties (wire-bound / passed from host)
    // -----------------------------------------------------------------

    #[Locked]
    public string $bookingPublicId = '';

    #[Locked]
    public string $threadPublicId = '';

    public string $body = '';

    public string $flashMessage = '';

    public bool $isBlocked = false;

    public bool $historyUnavailable = false;

    public ?string $historyPageToken = null;

    /** @var list<array{id:string,senderUserId:string,body:?string,createdAt:string,blocked:bool,redacted:bool}> */
    public array $historyMessages = [];

    // -----------------------------------------------------------------
    // Private state (populated in mount, not wire-bound)
    // -----------------------------------------------------------------

    private ?Booking $booking = null;

    private ?ChatThread $thread = null;

    private ?VendorProfile $vendorProfile = null;

    // -----------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------

    public function mount(string $bookingPublicId = '', string $threadPublicId = ''): void
    {
        $this->bookingPublicId = $bookingPublicId;
        $this->threadPublicId = $threadPublicId;
        $this->resolveContext();
        $this->refreshMessages();
    }

    public function hydrate(): void
    {
        $this->resolveContext();
    }

    private function resolveContext(): void
    {
        if ($this->threadPublicId !== '') {
            abort_unless(auth()->user()?->hasRole('customer'), 403);
            $this->thread = ChatThread::query()
                ->where('public_id', $this->threadPublicId)
                ->where('customer_id', auth()->id())
                ->with(['booking', 'bookingVendor', 'frozenByUser'])
                ->firstOrFail();
            $this->booking = $this->thread->booking;
            abort_if($this->booking === null, 404);
            $this->bookingPublicId = (string) $this->booking->public_id;
            $this->vendorProfile = $this->thread->vendorProfile;

            return;
        }

        abort_unless(VendorOperationalAccess::allowed(), 403);
        // Load vendor profile for the authenticated user
        $this->vendorProfile = VendorProfile::query()
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Load booking via public_id
        $this->booking = Booking::query()
            ->where('public_id', $this->bookingPublicId)
            ->whereHas('vendors', fn ($query) => $query->where('vendor_profile_id', $this->vendorProfile->id))
            ->firstOrFail();

        // Load chat thread for this booking+vendor (may be null)
        $this->thread = ChatThread::query()
            ->where('booking_id', $this->booking->id)
            ->where('vendor_profile_id', $this->vendorProfile->id)
            ->first();
    }

    // -----------------------------------------------------------------
    // Computed properties
    // -----------------------------------------------------------------

    #[Computed]
    public function state(): ChatPanelState
    {
        return app(ChatPanelStateResolver::class)
            ->resolve($this->thread, $this->booking);
    }

    #[Computed]
    public function messages(): Collection
    {
        return collect($this->historyMessages);
    }

    #[Computed]
    public function frozenByName(): ?string
    {
        return $this->thread?->frozenByUser?->name;
    }

    public function refreshMessages(): void
    {
        if ($this->thread === null) {
            return;
        }

        try {
            $page = app(FirestoreChatGateway::class)->listMessages($this->thread->firestore_thread_id ?? '');
            $this->historyPageToken = $page->nextPageToken;
            $this->historyMessages = array_map(static fn ($message): array => [
                'id' => $message->id,
                'senderUserId' => $message->senderUserId,
                'body' => $message->body,
                'createdAt' => $message->createdAt->toIso8601String(),
                'blocked' => $message->blocked,
                'redacted' => $message->redacted,
            ], $page->messages);
            $this->historyUnavailable = false;
            unset($this->messages);
        } catch (Throwable $exception) {
            Log::warning('communication.booking_chat.history_load_failed', [
                'thread_id' => $this->thread->id,
                'error_class' => class_basename($exception),
            ]);
            $this->historyUnavailable = true;
        }
    }

    public function loadEarlierMessages(): void
    {
        if ($this->thread === null || $this->historyPageToken === null) {
            return;
        }

        try {
            $page = app(FirestoreChatGateway::class)->listMessages($this->thread->firestore_thread_id ?? '', $this->historyPageToken);
            $this->historyPageToken = $page->nextPageToken;
            $this->historyMessages = [...$this->historyMessages, ...array_map(static fn ($message): array => [
                'id' => $message->id,
                'senderUserId' => $message->senderUserId,
                'body' => $message->body,
                'createdAt' => $message->createdAt->toIso8601String(),
                'blocked' => $message->blocked,
                'redacted' => $message->redacted,
            ], $page->messages)];
            unset($this->messages);
        } catch (Throwable $exception) {
            Log::warning('communication.booking_chat.history_load_failed', [
                'thread_id' => $this->thread->id,
                'error_class' => class_basename($exception),
            ]);
            $this->historyUnavailable = true;
        }
    }

    // -----------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------

    public function sendMessage(): void
    {
        $this->validate([
            'body' => 'required|string|max:2000',
        ]);

        if ($this->state() !== ChatPanelState::Open || $this->thread === null) {
            $this->flashMessage = __('communication::chat.system_locked_notice');
            $this->isBlocked = true;

            return;
        }

        try {
            if ($this->threadPublicId !== '') {
                app(SendCustomerChatMessageAction::class)->execute($this->thread, (int) auth()->id(), $this->body);
            } else {
                app(SendVendorChatMessageAction::class)->execute(
                    $this->thread,
                    $this->vendorProfile,
                    new SendVendorChatMessageDTO($this->body),
                );
            }
        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Duplicate message submission')) {
                return;
            }

            $this->flashMessage = __('communication::chat.message_blocked');
            $this->isBlocked = true;

            return;
        }

        catch (Throwable $e) {
            $this->flashMessage = __('communication::chat.delivery_failed');
            $this->isBlocked = true;

            return;
        }

        $this->body = '';
        $this->isBlocked = false;
        $this->flashMessage = __('communication::chat.message_sent');

        // Refresh thread so the messages computed property returns fresh rows
        $this->thread = $this->thread->fresh();
        $this->refreshMessages();
    }

    // -----------------------------------------------------------------
    // Rendering
    // -----------------------------------------------------------------

    public function render(): View
    {
        return view($this->threadPublicId !== ''
            ? 'communication::storefront.account.booking-chat-panel'
            : 'vendor-portal.components.restricted-chat-panel');
    }
}
