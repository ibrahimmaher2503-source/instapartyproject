<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Events\VendorSuspended;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class SuspendVendorTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly ?int $actorId,
        private readonly string $reason,
        private readonly bool $system = false,
        private readonly bool $emitEvent = true,
    ) {}

    public function handle(): VendorProfile
    {
        abort_unless($this->system || User::query()->find($this->actorId)?->can('suspend_vendor'), 403, 'Insufficient permissions to suspend vendor');

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', $this->system ? TriggerKind::System->value : TriggerKind::Admin->value);
        Context::add('transition_reason', $this->reason);

        $this->model->approval_status = new SuspendedState($this->model);
        $this->model->suspended_at = now();
        $this->model->suspended_by = $this->actorId;
        $this->model->save();

        if ($this->emitEvent) {
            DB::afterCommit(fn () => event(new VendorSuspended($this->model, $this->actorId)));
        }

        return $this->model;
    }
}
