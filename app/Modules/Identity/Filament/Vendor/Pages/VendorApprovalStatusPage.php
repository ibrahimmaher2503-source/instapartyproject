<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Application\Actions\GetVendorApprovalStatusAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ChangesRequestedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\RejectedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\VendorApprovalState;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class VendorApprovalStatusPage extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'vendor-portal.pages.vendor-approval-status';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.approval.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.approval.title');
    }

    public function approvalInfolist(Infolist $schema): Infolist
    {
        $profile = $this->getVendorProfile();
        $status = app(GetVendorApprovalStatusAction::class)->execute($profile);

        $typeEntries = [];
        foreach (ProductType::cases() as $type) {
            $typeStatus = $status['types'][$type->value] ?? ['approved' => false, 'approved_at' => null];
            $typeEntries[] = TextEntry::make('type_'.$type->value)
                ->label(ucfirst($type->value))
                ->badge()
                ->state($typeStatus['approved'] ? __('vendor-portal.approval.approved') : __('vendor-portal.approval.pending'))
                ->color($typeStatus['approved'] ? 'success' : 'warning');
        }

        return $schema
            ->record($profile)
            ->components([
                Section::make(__('vendor-portal.approval.overall'))
                    ->schema([
                        TextEntry::make('approval_status')
                            ->label(__('vendor-portal.approval.overall'))
                            ->badge()
                            ->formatStateUsing(fn (VendorApprovalState $state): string => match (true) {
                                $state instanceof PendingState => __('vendor-portal.approval.pending'),
                                $state instanceof ApprovedState => __('vendor-portal.approval.approved'),
                                $state instanceof RejectedState => __('vendor-portal.approval.rejected'),
                                $state instanceof SuspendedState => __('vendor-portal.approval.suspended'),
                                $state instanceof ChangesRequestedState => __('identity.status.changes_requested'),
                                default => (string) $state,
                            })
                            ->color(fn (VendorApprovalState $state): string => match (true) {
                                $state instanceof PendingState => 'warning',
                                $state instanceof ApprovedState => 'success',
                                $state instanceof RejectedState => 'danger',
                                $state instanceof SuspendedState => 'danger',
                                $state instanceof ChangesRequestedState => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('rejection_reason')
                            ->label(__('vendor-portal.approval.rejection_reason'))
                            ->formatStateUsing(fn ($record) => $record->getTranslation('rejection_reason', app()->getLocale()) ?? '—')
                            ->visible(fn (VendorProfile $record): bool => $record->approval_status instanceof RejectedState),
                    ]),

                Section::make(__('vendor-portal.approval.per_type'))
                    ->schema($typeEntries)
                    ->columns(3),
            ]);
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
