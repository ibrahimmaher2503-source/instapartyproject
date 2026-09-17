<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ChangesRequestedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class RequestVendorChangesTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly int $actorId,
        private readonly ?array $rejectionReason = null,
    ) {}

    public function handle(): VendorProfile
    {
        abort_unless(
            User::query()->find($this->actorId)?->can('manage_vendor_profile'),
            403,
            'Insufficient permissions to request vendor changes'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->approval_status = new ChangesRequestedState($this->model);

        if ($this->rejectionReason !== null) {
            $this->model->rejection_reason = $this->rejectionReason;
        }
        $this->model->save();

        return $this->model;
    }
}
