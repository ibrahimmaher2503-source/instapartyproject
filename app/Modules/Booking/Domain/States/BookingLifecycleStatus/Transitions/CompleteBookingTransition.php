<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions;

use App\Modules\Booking\Domain\Events\BookingCompleted;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class CompleteBookingTransition extends Transition
{
    public function __construct(
        private readonly Booking $model,
    ) {}

    public function handle(): Booking
    {
        Context::add('trigger_kind', TriggerKind::System->value);

        // Transition handlers are already invoked by State::transitionTo();
        // calling transitionTo() again here recurses forever.
        $this->model->setAttribute('lifecycle_status', CompletedState::class);
        $this->model->save();

        DB::afterCommit(fn () => event(new BookingCompleted($this->model)));

        return $this->model;
    }
}
