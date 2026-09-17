<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Widgets;

use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistDTO;
use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistItemDTO;
use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\RejectionState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Filament\Notifications\Notification;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VendorOnboardingChecklistWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        $profile = auth()->user()?->vendorProfile;

        // Onboarding checklist is only relevant until the vendor is approved.
        // Once approved, the operational dashboard widgets take over (BUG-010).
        return $profile !== null && ! ($profile->approval_status instanceof ApprovedState);
    }

    public function mount(): void
    {
        $checklist = $this->getChecklist();

        if ($checklist === null) {
            return;
        }

        if ($checklist->isSuspended) {
            Notification::make()
                ->title(__('identity::vendor-onboarding.banner.suspended_heading'))
                ->body(trim(
                    ($checklist->suspendedAt
                        ? __('identity::vendor-onboarding.banner.suspended_since', [
                            'date' => $checklist->suspendedAt->translatedFormat('d M Y'),
                        ])
                        : '')
                    .' '.($checklist->suspensionReason ?? '')
                ))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($checklist->rejectionState !== RejectionState::None) {
            $isRejected = $checklist->rejectionState === RejectionState::Rejected;

            $notification = Notification::make()
                ->title($isRejected
                    ? __('identity::vendor-onboarding.banner.rejected_heading')
                    : __('identity::vendor-onboarding.banner.changes_requested_heading'))
                ->persistent();

            if ($checklist->rejectionReason !== null) {
                $notification->body($checklist->rejectionReason);
            }

            $isRejected ? $notification->danger()->send() : $notification->warning()->send();
        }
    }

    public function getHeading(): ?string
    {
        $checklist = $this->getChecklist();

        if ($checklist === null || $checklist->completedCount === $checklist->totalCount) {
            return null;
        }

        return __('identity::vendor-onboarding.progress', [
            'done' => $checklist->completedCount,
            'total' => $checklist->totalCount,
        ]).' · '.$checklist->progressPercent.'%';
    }

    public function getDescription(): ?string
    {
        $checklist = $this->getChecklist();

        if ($checklist === null || $checklist->nextRecommendedAction === null) {
            return null;
        }

        $next = $checklist->nextRecommendedAction;

        return __('identity::vendor-onboarding.cta.next_action').': '
            .__('identity::vendor-onboarding.rows.'.$next->key->value.'.cta');
    }

    protected function getStats(): array
    {
        $checklist = $this->getChecklist();

        if ($checklist === null || $checklist->completedCount === $checklist->totalCount) {
            return [];
        }

        return array_map(
            fn (VendorOnboardingChecklistItemDTO $item) => $this->itemToStat($item),
            $checklist->items,
        );
    }

    private function itemToStat(VendorOnboardingChecklistItemDTO $item): Stat
    {
        $stat = Stat::make(
            $item->label,
            __('identity::vendor-onboarding.status.'.$item->status->value),
        )
            ->descriptionIcon($this->iconFor($item->status))
            ->color($this->colorFor($item->status));

        if ($item->subText !== null) {
            $stat->description($item->subText);
        }

        if ($item->url !== null) {
            $stat->url($item->url);
        }

        return $stat;
    }

    private function iconFor(ChecklistItemStatus $status): string
    {
        return match ($status) {
            ChecklistItemStatus::Complete => 'heroicon-o-check-circle',
            ChecklistItemStatus::Danger => 'heroicon-o-x-circle',
            ChecklistItemStatus::Warning => 'heroicon-o-exclamation-triangle',
            ChecklistItemStatus::Info => 'heroicon-o-information-circle',
            ChecklistItemStatus::Pending => 'heroicon-o-minus-circle',
        };
    }

    private function colorFor(ChecklistItemStatus $status): string
    {
        return match ($status) {
            ChecklistItemStatus::Complete => 'success',
            ChecklistItemStatus::Danger => 'danger',
            ChecklistItemStatus::Warning => 'warning',
            ChecklistItemStatus::Info => 'info',
            ChecklistItemStatus::Pending => 'gray',
        };
    }

    private function getChecklist(): ?VendorOnboardingChecklistDTO
    {
        /** @var User|null $user */
        $user = auth()->user();

        $vendorProfile = $user?->vendorProfile;

        if ($vendorProfile === null) {
            return null;
        }

        return app(VendorOnboardingChecklistService::class)->forVendor($vendorProfile);
    }
}
