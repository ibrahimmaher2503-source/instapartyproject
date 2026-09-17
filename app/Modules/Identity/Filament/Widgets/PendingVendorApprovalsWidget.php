<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Widgets;

use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingVendorApprovalsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 3,
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_vendor_profile') ?? false;
    }

    protected function getStats(): array
    {
        $count = VendorProfile::query()
            ->where('approval_status', ApprovalStatus::Pending->value)
            ->count();

        return [
            Stat::make(
                label: __('identity::widgets.pending_vendor_approvals_heading'),
                value: $count,
            )
                ->description(trans_choice('identity::widgets.pending_vendor_approvals_description', $count, ['count' => $count]))
                ->color($count > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-user-plus')
                ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/admin' : VendorApprovalQueueResource::getUrl('index')),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }
}
