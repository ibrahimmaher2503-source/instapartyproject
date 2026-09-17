<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Events\VendorApproved;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class ApproveVendorTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly int $actorId,
    ) {}

    public function handle(): VendorProfile
    {
        abort_unless(
            User::query()->find($this->actorId)?->can('approve_vendor_profile'),
            403,
            'Insufficient permissions to approve vendor'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->approval_status = new ApprovedState($this->model);
        $this->model->approved_at = now();
        $this->model->approved_by = $this->actorId;
        $this->model->save();

        DB::afterCommit(fn () => event(new VendorApproved($this->model, $this->actorId)));

        return $this->model;
    }
}
