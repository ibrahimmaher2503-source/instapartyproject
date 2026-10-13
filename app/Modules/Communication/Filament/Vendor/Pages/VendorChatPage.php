<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Vendor\Pages;

use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use App\Modules\Shared\Application\Services\VendorOperationalAccess;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class VendorChatPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedVendor;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'bookings';

    protected static string $view = 'vendor-portal.pages.vendor-chat';

    public static function getNavigationLabel(): string
    {
        return __('communication::chat.inbox_title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function table(Table $table): Table
    {
        abort_unless(VendorOperationalAccess::allowed(), 403);
        $vendorId = auth()->user()->vendorProfile->id;

        return $table->query(ChatThread::query()
            ->where('vendor_profile_id', $vendorId)
            ->whereHas('booking.vendors', fn ($query) => $query->where('vendor_profile_id', $vendorId))
            ->with(['booking', 'customer']))
            ->columns([
                TextColumn::make('booking.reference_no')->label(__('communication::chat.booking'))->searchable(),
                TextColumn::make('customer.name')->label(__('communication::chat.customer'))->searchable(),
                TextColumn::make('status')->label(__('communication::chat.status'))
                    ->formatStateUsing(fn (string $state): string => __('communication::chat.thread_status.'.$state)),
                TextColumn::make('created_at')->label(__('communication::chat.created_at'))->dateTime()->timezone(auth()->user()->timezone ?? 'Africa/Cairo'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (ChatThread $record): string => VendorChatConversationPage::getUrl(['thread' => $record->public_id], panel: 'vendor'))
            ->emptyStateHeading(__('communication::chat.no_threads'));
    }

    public function hydrate(): void
    {
        abort_unless(VendorOperationalAccess::allowed(), 403);
    }
}
