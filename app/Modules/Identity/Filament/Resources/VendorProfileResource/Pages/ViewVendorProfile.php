<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\RevokeVendorTypeAction;
use App\Modules\Identity\Application\Actions\SuspendVendorAction;
use App\Modules\Identity\Application\Actions\UnsuspendVendorAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorProfile extends ViewRecord
{
    protected static string $resource = VendorProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveForRental')
                ->label(__('identity.actions.approve_for_rental'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (VendorProfile $record): bool => $this->canApproveType($record, ProductType::Rental))
                ->requiresConfirmation()
                ->action(fn (VendorProfile $record) => $this->approveType($record, ProductType::Rental)),

            Action::make('approveForSale')
                ->label(__('identity.actions.approve_for_sale'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (VendorProfile $record): bool => $this->canApproveType($record, ProductType::Sale))
                ->requiresConfirmation()
                ->action(fn (VendorProfile $record) => $this->approveType($record, ProductType::Sale)),

            Action::make('approveForDigital')
                ->label(__('identity.actions.approve_for_digital'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (VendorProfile $record): bool => $this->canApproveType($record, ProductType::Digital))
                ->requiresConfirmation()
                ->action(fn (VendorProfile $record) => $this->approveType($record, ProductType::Digital)),

            Action::make('revokeType')
                ->label(__('identity.actions.revoke_type_short'))
                ->icon('heroicon-o-minus-circle')
                ->color('warning')
                ->visible(fn (VendorProfile $record): bool => (bool) auth()->user()?->can('revoke_vendor_type')
                    && $record->approvedTypes()->whereNull('revoked_at')->exists())
                ->form([
                    Select::make('product_type')
                        ->label(__('identity.fields.product_type'))
                        ->options([
                            'rental' => __('identity.product_type.rental'),
                            'sale' => __('identity.product_type.sale'),
                            'digital' => __('identity.product_type.digital'),
                        ])
                        ->required(),
                    Textarea::make('revoke_reason_en')
                        ->label(__('identity.forms.revoke_reason_en_short'))
                        ->required()
                        ->maxLength(1000),
                    Textarea::make('revoke_reason_ar')
                        ->label(__('identity.forms.revoke_reason_ar'))
                        ->maxLength(1000)
                        ->extraInputAttributes(['dir' => 'rtl']),
                ])
                ->action(function (VendorProfile $record, array $data): void {
                    $reason = array_filter(['en' => $data['revoke_reason_en'] ?? null, 'ar' => $data['revoke_reason_ar'] ?? null]);
                    app(RevokeVendorTypeAction::class)->execute($record, ProductType::from($data['product_type']), $reason);
                    Notification::make()->title(__('identity.notifications.type_revoked'))->warning()->send();
                }),

            Action::make('suspend')
                ->label(__('identity.actions.suspend_vendor'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (VendorProfile $record): bool => $record->approval_status instanceof ApprovedState
                    && (bool) auth()->user()?->can('suspend_vendor'))
                ->action(function (VendorProfile $record): void {
                    app(SuspendVendorAction::class)->execute($record);
                    Notification::make()->title(__('identity.notifications.vendor_suspended'))->danger()->send();
                }),

            Action::make('unsuspend')
                ->label(__('identity.actions.unsuspend'))
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (VendorProfile $record): bool => $record->approval_status instanceof SuspendedState
                    && (bool) auth()->user()?->can('suspend_vendor'))
                ->action(function (VendorProfile $record): void {
                    app(UnsuspendVendorAction::class)->execute($record);
                    Notification::make()->title(__('identity.notifications.vendor_unsuspended'))->success()->send();
                    $this->refreshFormData(['approval_status']);
                }),
        ];
    }

    private function canApproveType(VendorProfile $record, ProductType $type): bool
    {
        if (! ($record->approval_status instanceof ApprovedState)) {
            return false;
        }

        if (! auth()->user()?->can('approve_vendor_for_type')) {
            return false;
        }

        return ! $record->approvedTypes()
            ->where('product_type', $type->value)
            ->whereNull('revoked_at')
            ->exists();
    }

    private function approveType(VendorProfile $record, ProductType $type): void
    {
        app(ApproveVendorForTypeAction::class)->execute($record, $type);
        Notification::make()->title(__('identity.notifications.type_approved_for', ['type' => __('identity.product_type.'.$type->value)]))->success()->send();
        $this->refreshFormData(['approval_status']);
    }
}
