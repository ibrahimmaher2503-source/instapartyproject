<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\ChatThreadResource\Pages;

use App\Modules\Communication\Application\Actions\RecordChatModerationReadAction;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Actions\FreezeChatAction;
use App\Modules\Communication\Filament\Actions\UnfreezeChatAction;
use App\Modules\Communication\Filament\Resources\ChatThreadResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewChatThread extends ViewRecord
{
    protected static string $resource = ChatThreadResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record instanceof ChatThread, 404);

        $actorId = Auth::guard('web')->id();
        app(RecordChatModerationReadAction::class)->execute($this->record, $actorId === null ? null : (int) $actorId);
    }

    /**
     * SC-007 + FR-EXT-036-004: no Edit/Delete header actions ever appear on the read-only detail page.
     */
    protected function getHeaderActions(): array
    {
        return [
            FreezeChatAction::make(),
            UnfreezeChatAction::make(),
        ];
    }
}
