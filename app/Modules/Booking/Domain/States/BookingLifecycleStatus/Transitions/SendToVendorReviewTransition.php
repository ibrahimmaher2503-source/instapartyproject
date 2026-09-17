<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions;

use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class SendToVendorReviewTransition extends Transition
{
    public function __construct(
        private readonly Booking $model,
        private readonly int $actorId,
    ) {}

    public function handle(): Booking
    {
        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::System->value);

        $this->model->lifecycle_status->transitionTo(VendorReviewState::class);

        $events = $this->model->vendors()
            ->get()
            ->map(fn ($bookingVendor) => new BookingSubmittedToVendor($bookingVendor, $this->model));

        DB::afterCommit(static function () use ($events): void {
            foreach ($events as $event) {
                event($event);
            }
        });

        return $this->model;
    }
}
