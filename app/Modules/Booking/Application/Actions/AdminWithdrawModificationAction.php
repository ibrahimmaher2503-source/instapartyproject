<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Support\Facades\DB;

class AdminWithdrawModificationAction
{
    public function execute(BookingModification $modification, int $adminUserId): void
    {
        DB::transaction(function () use ($modification, $adminUserId): void {
            $modification->lockForUpdate();

            abort_if(
                ! in_array($modification->status, [ModificationStatus::Draft, ModificationStatus::Pending], strict: true),
                409,
                'Modification cannot be withdrawn in its current status.',
            );

            $previous = $modification->status->value;

            $modification->update(['status' => ModificationStatus::Withdrawn]);

            StateTransition::create([
                'transitionable_type' => BookingModification::class,
                'transitionable_id' => $modification->id,
                'from_state' => $previous,
                'to_state' => ModificationStatus::Withdrawn->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $adminUserId,
                'context' => ['admin_withdrawn' => true],
            ]);
        });
    }
}
