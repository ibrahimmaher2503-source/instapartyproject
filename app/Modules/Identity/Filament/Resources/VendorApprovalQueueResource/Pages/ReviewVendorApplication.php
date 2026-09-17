<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\RejectVendorProfileAction;
use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ReviewVendorApplication extends ViewRecord
{
    protected static string $resource = VendorApprovalQueueResource::class;

    public function getTitle(): string
    {
        return __('identity.pages.review_vendor_application');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('identity.actions.approve_profile'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('identity.modals.approve_vendor_heading'))
                ->modalDescription(__('identity.modals.approve_vendor_description'))
                ->visible(fn (VendorProfile $record): bool => $record->approval_status instanceof PendingState
                    && (bool) auth()->user()?->can('approve_vendor_profile'))
                ->disabled(fn (VendorProfile $record): bool => ! app(VendorApprovalEligibilityService::class)->evaluate($record)->eligible)
                ->tooltip(fn (VendorProfile $record): ?string => app(VendorApprovalEligibilityService::class)->evaluate($record)->eligible
                    ? null
                    : (string) __('identity.approval_eligibility.complete_requirements'))
                ->action(function (VendorProfile $record): void {
                    app(ApproveVendorProfileAction::class)->execute($record);
                    Notification::make()->title(__('identity.notifications.vendor_approved'))->success()->send();
                    $this->redirect(VendorApprovalQueueResource::getUrl('index'));
                }),

            Action::make('approveForRental')
                ->label(__('identity.actions.approve_for_rental'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (VendorProfile $record): bool => auth()->user()?->can('approve_vendor_for_type')
                    && $record->approval_status instanceof ApprovedState
                    && ! $record->approvedTypes()->where('product_type', 'rental')->whereNull('revoked_at')->exists()
                )
                ->requiresConfirmation()
                ->action(function (VendorProfile $record): void {
                    app(ApproveVendorForTypeAction::class)->execute($record, ProductType::Rental);
                    Notification::make()->title(__('identity.notifications.type_approved_for', ['type' => __('identity.product_type.rental')]))->success()->send();
                    $this->refreshFormData([]);
                }),

            Action::make('approveForSale')
                ->label(__('identity.actions.approve_for_sale'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (VendorProfile $record): bool => auth()->user()?->can('approve_vendor_for_type')
                    && $record->approval_status instanceof ApprovedState
                    && ! $record->approvedTypes()->where('product_type', 'sale')->whereNull('revoked_at')->exists()
                )
                ->requiresConfirmation()
                ->action(function (VendorProfile $record): void {
                    app(ApproveVendorForTypeAction::class)->execute($record, ProductType::Sale);
                    Notification::make()->title(__('identity.notifications.type_approved_for', ['type' => __('identity.product_type.sale')]))->success()->send();
                    $this->refreshFormData([]);
                }),

            Action::make('approveForDigital')
                ->label(__('identity.actions.approve_for_digital'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (VendorProfile $record): bool => auth()->user()?->can('approve_vendor_for_type')
                    && $record->approval_status instanceof ApprovedState
                    && ! $record->approvedTypes()->where('product_type', 'digital')->whereNull('revoked_at')->exists()
                )
                ->requiresConfirmation()
                ->action(function (VendorProfile $record): void {
                    app(ApproveVendorForTypeAction::class)->execute($record, ProductType::Digital);
                    Notification::make()->title(__('identity.notifications.type_approved_for', ['type' => __('identity.product_type.digital')]))->success()->send();
                    $this->refreshFormData([]);
                }),

            Action::make('reject')
                ->label(__('identity.actions.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (VendorProfile $record): bool => $record->approval_status instanceof PendingState
                    && (bool) auth()->user()?->can('reject_vendor_profile'))
                ->form([
                    Textarea::make('rejection_reason_en')
                        ->label(__('identity.forms.rejection_reason_en'))
                        ->required()
                        ->maxLength(1000),
                    Textarea::make('rejection_reason_ar')
                        ->label(__('identity.forms.rejection_reason_ar'))
                        ->maxLength(1000),
                ])
                ->action(function (VendorProfile $record, array $data): void {
                    $reason = array_filter([
                        'en' => $data['rejection_reason_en'] ?? null,
                        'ar' => $data['rejection_reason_ar'] ?? null,
                    ]);
                    app(RejectVendorProfileAction::class)->execute($record, $reason);
                    Notification::make()->title(__('identity.notifications.vendor_rejected'))->danger()->send();
                    $this->redirect(VendorApprovalQueueResource::getUrl('index'));
                }),
        ];
    }
}
