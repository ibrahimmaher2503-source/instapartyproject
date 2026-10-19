<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;

use App\Modules\Booking\Application\Actions\AdminDecidePendingBookingVendorAction;
use App\Modules\Booking\Domain\Enums\AdminVendorDecision;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Shared\Application\Services\StorefrontText;
use DomainException;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingVendorsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'vendors';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('booking.relations.vendors');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vendor']))
            ->columns([
                TextColumn::make('vendor_profile_id')
                    ->label(__('booking.columns.vendor'))
                    ->getStateUsing(fn (BookingVendor $record): string => self::resolveVendorName($record))
                    ->searchable(),
                TextColumn::make('sub_status')
                    ->label(__('booking.columns.vendor_status'))
                    ->badge()
                    ->color(fn (VendorSubStatus $state): string => match ($state) {
                        VendorSubStatus::Pending => 'warning',
                        VendorSubStatus::Accepted, VendorSubStatus::InProgress => 'info',
                        VendorSubStatus::Completed => 'success',
                        VendorSubStatus::Modified => 'warning',
                        VendorSubStatus::Rejected, VendorSubStatus::Cancelled => 'danger',
                        VendorSubStatus::TimedOut => 'gray',
                    })
                    ->formatStateUsing(fn (VendorSubStatus $state): string => __('booking.vendor_status.'.$state->value)),
                TextColumn::make('response_deadline')
                    ->label(__('booking.columns.response_deadline'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('subtotal_minor')
                    ->label(__('booking.columns.vendor_subtotal'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([
                Action::make('admin_accept_vendor')
                    ->label(__('booking::booking.intervention.actions.admin_accept_vendor'))
                    ->color('success')
                    ->visible(fn (BookingVendor $record): bool => self::canDecide($record))
                    ->form([Textarea::make('reason')->label(__('booking::booking.intervention.fields.admin_decision_reason'))
                        ->required()->minLength(10)->maxLength(1000)->rows(3)])
                    ->action(function (BookingVendor $record, array $data): void {
                        try {
                            app(AdminDecidePendingBookingVendorAction::class)->execute($record, AdminVendorDecision::Accept, $data['reason']);
                            Notification::make()->title(__('booking::booking.intervention.success.admin_accept_vendor'))->success()->send();
                        } catch (ResponseDeadlineExpiredException|DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.admin_accept_vendor')),
                Action::make('admin_reject_vendor')
                    ->label(__('booking::booking.intervention.actions.admin_reject_vendor'))
                    ->color('danger')
                    ->visible(fn (BookingVendor $record): bool => self::canDecide($record))
                    ->form([Textarea::make('reason')->label(__('booking::booking.intervention.fields.admin_decision_reason'))
                        ->required()->minLength(10)->maxLength(1000)->rows(3)])
                    ->action(function (BookingVendor $record, array $data): void {
                        try {
                            app(AdminDecidePendingBookingVendorAction::class)->execute($record, AdminVendorDecision::Reject, $data['reason']);
                            Notification::make()->title(__('booking::booking.intervention.success.admin_reject_vendor'))->success()->send();
                        } catch (ResponseDeadlineExpiredException|DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(__('booking::booking.intervention.confirm.admin_reject_vendor')),
            ])
            ->bulkActions([])
            ->emptyStateHeading(__('booking.relations.vendors'))
            ->emptyStateDescription(__('booking.empty_states.vendors'))
            ->defaultSort('created_at', 'desc');
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }

    private static function resolveVendorName(BookingVendor $record): string
    {
        $vendor = $record->vendor;
        if (! $vendor) {
            return '#'.$record->vendor_profile_id;
        }

        $value = app(StorefrontText::class)->translation($vendor, 'business_name');

        return is_string($value) && $value !== '' ? $value : '#'.$record->vendor_profile_id;
    }

    private static function canDecide(BookingVendor $record): bool
    {
        return $record->sub_status === VendorSubStatus::Pending
            && $record->response_deadline?->isFuture() === true
            && auth()->user()?->can('booking.intervene.decide_vendor_request') === true;
    }
}
