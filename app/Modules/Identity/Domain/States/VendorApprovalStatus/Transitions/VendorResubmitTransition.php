<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class VendorResubmitTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly int $actorId,
    ) {}

    public function handle(): VendorProfile
    {
        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Vendor->value);

        $this->model->approval_status = new PendingState($this->model);
        $this->model->save();

        return $this->model;
    }
}
