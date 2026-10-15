<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Vendor\Pages;

use App\Modules\Communication\Domain\Models\VendorInquiry;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use App\Modules\Shared\Application\Services\VendorOperationalAccess;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class VendorInquiryListPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedVendor;

    protected static ?string $slug = 'vendor-inquiries';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'bookings';

    protected static string $view = 'vendor-portal.pages.vendor-inquiries';

    public static function getNavigationLabel(): string
    {
        return __('communication::communication.vendor_inquiries.inbox_title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function table(Table $table): Table
    {
        abort_unless(VendorOperationalAccess::allowed(), 403);
        $vendorProfileId = (int) auth()->user()->vendorProfile->id;

        return $table->query(VendorInquiry::query()
            ->forVendor($vendorProfileId)
            ->withCount('messages'))
            ->columns([
                TextColumn::make('service_name_en')
                    ->label(__('communication::communication.vendor_inquiries.service'))
                    ->formatStateUsing(fn (string $state, VendorInquiry $record): string => $record->serviceNameFor(app()->getLocale()))
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label(__('communication::communication.vendor_inquiries.customer'))
                    ->searchable(),
                TextColumn::make('messages_count')
                    ->label(__('communication::communication.vendor_inquiries.messages')),
                TextColumn::make('updated_at')
                    ->label(__('communication::communication.vendor_inquiries.last_message'))
                    ->dateTime(timezone: auth()->user()->timezone ?? 'Africa/Cairo')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (VendorInquiry $record): string => VendorInquiryConversationPage::getUrl(
                ['inquiry' => $record->public_id],
                panel: 'vendor',
            ))
            ->emptyStateHeading(__('communication::communication.vendor_inquiries.empty_title'));
    }

    public function hydrate(): void
    {
        abort_unless(VendorOperationalAccess::allowed(), 403);
    }
}
