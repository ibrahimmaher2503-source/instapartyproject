<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Events\VendorUnsuspended;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class UnsuspendVendorTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly int $actorId,
    ) {}

    public function handle(): VendorProfile
    {
        abort_unless(
            User::query()->find($this->actorId)?->can('suspend_vendor'),
            403,
            'Insufficient permissions to unsuspend vendor'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->approval_status = new ApprovedState($this->model);
        $this->model->suspended_at = null;
        $this->model->suspended_by = null;
        $this->model->save();

        DB::afterCommit(fn () => event(new VendorUnsuspended($this->model, $this->actorId)));

        return $this->model;
    }
}
