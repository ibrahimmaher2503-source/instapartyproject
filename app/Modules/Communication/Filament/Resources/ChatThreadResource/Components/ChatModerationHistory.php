<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\ChatThreadResource\Components;

use App\Modules\Communication\Application\Actions\RecordChatModerationReadAction;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\Models\ChatThread;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

final class ChatModerationHistory extends Component
{
    #[Locked]
    public string $threadPublicId = '';

    /** @var list<array{sender:string,body:?string,created_at:string,redacted:bool}> */
    #[Locked]
    public array $messages = [];

    #[Locked]
    public ?string $historyPageToken = null;

    #[Locked]
    public bool $historyUnavailable = false;

    public function mount(ChatThread $record): void
    {
        $this->threadPublicId = $record->public_id;
        $this->readPage();
    }

    public function hydrate(): void
    {
        $this->thread();
    }

    public function loadEarlierMessages(): void
    {
        if ($this->historyPageToken !== null) {
            $this->readPage($this->historyPageToken, append: true);
        }
    }

    public function refreshMessages(): void
    {
        $this->readPage();
    }

    public function render(): View
    {
        return view('communication::filament.chat-moderation-history');
    }

    private function readPage(?string $pageToken = null, bool $append = false): void
    {
        $thread = $this->thread();
        $actorId = Auth::guard('web')->id();
        app(RecordChatModerationReadAction::class)->execute($thread, $actorId === null ? null : (int) $actorId);

        try {
            $page = app(FirestoreChatGateway::class)->listMessages($thread->firestore_thread_id, $pageToken);
            $thread->loadMissing(['customer:id,name', 'vendorProfile.user:id,name']);
            $participantNames = collect([$thread->customer, $thread->vendorProfile?->user])
                ->filter()
                ->keyBy('id');
            $participantIds = array_values(array_unique(array_filter([
                (int) $thread->customer_id,
                (int) $thread->vendorProfile?->user_id,
            ], static fn (int $id): bool => $id > 0)));
            $messages = array_map(static function ($message) use ($participantNames, $participantIds): array {
                $senderId = (int) $message->senderUserId;
                $sender = in_array($senderId, $participantIds, true)
                    ? ($participantNames->get($senderId)?->name ?? __('chat_moderation.deleted_sender'))
                    : __('chat_moderation.unknown_sender');
                $redacted = $message->redacted || $message->blocked;

                return [
                    'sender' => $sender,
                    'body' => $redacted ? null : $message->body,
                    'created_at' => $message->createdAt->toIso8601String(),
                    'redacted' => $redacted,
                ];
            }, $page->messages);

            $this->messages = $append ? [...$this->messages, ...$messages] : $messages;
            $this->historyPageToken = $page->nextPageToken;
            $this->historyUnavailable = false;
        } catch (Throwable $exception) {
            Log::warning('communication.admin_chat.history_load_failed', [
                'thread_id' => $thread->getKey(),
                'error_class' => class_basename($exception),
            ]);
            $this->historyUnavailable = true;
        }
    }

    private function thread(): ChatThread
    {
        abort_unless(Auth::guard('web')->user()?->can('chat_moderation.view') === true, 403);

        return ChatThread::query()
            ->where('public_id', $this->threadPublicId)
            ->firstOrFail();
    }
}
