<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\FreezeChatAction;
use App\Modules\Communication\Application\Actions\UnfreezeChatAction;
use App\Modules\Communication\Application\DTOs\FreezeChatDTO;
use App\Modules\Communication\Application\DTOs\UnfreezeChatDTO;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\DTOs\FirestoreChatMessage;
use App\Modules\Communication\Domain\DTOs\FirestoreChatMessagePage;
use App\Modules\Communication\Domain\Enums\ChatFreezeCategory;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Resources\ChatMessageLogResource\Pages\ViewChatMessageLog;
use App\Modules\Communication\Filament\Resources\ChatModerationFlagResource\Pages\ViewChatModerationFlag;
use App\Modules\Communication\Filament\Resources\ChatThreadResource\Components\ChatModerationHistory;
use App\Modules\Communication\Filament\Resources\ChatThreadResource\Pages\ViewChatThread;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('does not let a caller freeze or unfreeze without the corresponding permission', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $thread = ChatThread::factory()->create();
    $this->actingAs($customer);
    expect(fn () => app(FreezeChatAction::class)->execute($thread, new FreezeChatDTO('Safety review', 'مراجعة السلامة', ChatFreezeCategory::Other), $customer))
        ->toThrow(HttpException::class);
    expect(fn () => app(UnfreezeChatAction::class)->execute($thread, new UnfreezeChatDTO('Review completed', 'اكتملت المراجعة'), $customer))
        ->toThrow(HttpException::class);
    expect($thread->fresh()->status)->toBe('open');
});

it('audits access to moderation details and hides redacted message content', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('chat_moderation.view', 'web'));
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $thread = ChatThread::factory()->create();
    $message = ChatMessageLog::factory()->redacted()->create(['chat_thread_id' => $thread->id, 'body' => 'SECRET REDACTED CONTENT']);
    $flag = ChatModerationFlag::factory()->create(['chat_message_log_id' => $message->id]);
    app()->instance(FirestoreChatGateway::class, new class implements FirestoreChatGateway
    {
        public function createThread(string $firestoreThreadId, string $bookingPublicId, string $customerUserId, string $vendorUserId): void {}

        public function freezeThread(string $firestoreThreadId): void {}

        public function unfreezeThread(string $firestoreThreadId): void {}

        public function sendMessage(string $firestoreThreadId, string $senderUserId, string $body, ?string $messageId = null): string
        {
            return $messageId ?? 'message-id';
        }

        public function listMessages(string $firestoreThreadId, ?string $pageToken = null): FirestoreChatMessagePage
        {
            if ($pageToken !== null) {
                return new FirestoreChatMessagePage([
                    new FirestoreChatMessage('older-message', (string) $this->customerId, 'OLDER FIRESTORE BODY', Carbon::parse('2026-10-16 09:00:00', 'UTC'), false, false),
                ], null);
            }

            return new FirestoreChatMessagePage([
                new FirestoreChatMessage('visible-message', (string) $this->customerId, 'VISIBLE FIRESTORE BODY', Carbon::parse('2026-10-16 10:00:00', 'UTC'), false, false),
                new FirestoreChatMessage('redacted-message', (string) $this->vendorUserId, 'SECRET FIRESTORE BODY', Carbon::parse('2026-10-16 10:01:00', 'UTC'), true, true),
            ], 'older-page');
        }

        public int $customerId = 0;

        public int $vendorUserId = 0;
    });
    app(FirestoreChatGateway::class)->customerId = (int) $thread->customer_id;
    app(FirestoreChatGateway::class)->vendorUserId = (int) $thread->vendorProfile->user_id;
    foreach ([[ViewChatThread::class, $thread, 'chat_thread_viewed'], [ViewChatMessageLog::class, $message, 'chat_message_viewed'], [ViewChatModerationFlag::class, $flag, 'chat_moderation_flag_viewed']] as [$page, $record, $action]) {
        Livewire::test($page, ['record' => $record->getRouteKey()])->assertSuccessful()->assertDontSee('SECRET REDACTED CONTENT');
        expect(DB::table('audit_logs')->where('action', $action)->where('auditable_id', $record->id)->where('user_id', $admin->id)->exists())->toBeTrue();
    }
    Livewire::test(ChatModerationHistory::class, ['record' => $thread])
        ->assertSee('VISIBLE FIRESTORE BODY')
        ->assertSee($thread->customer->name)
        ->assertSee(__('chat_moderation.message_redacted'))
        ->assertDontSee('SECRET FIRESTORE BODY')
        ->assertSee(__('communication::chat.load_earlier'))
        ->call('loadEarlierMessages')
        ->assertSee('OLDER FIRESTORE BODY');
    $component = Livewire::test(ViewChatThread::class, ['record' => $thread->getRouteKey()]);
    $this->actingAs(User::factory()->asCustomer()->create());
    $component->call('$refresh')->assertForbidden();
});

it('keeps unauthorized users out of moderation details and does not audit a refused view', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $thread = ChatThread::factory()->create();
    $this->actingAs($customer);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ViewChatThread::class, ['record' => $thread->getRouteKey()])->assertForbidden();
    expect(DB::table('audit_logs')->where('action', 'chat_thread_viewed')->where('user_id', $customer->id)->exists())->toBeFalse();
});
