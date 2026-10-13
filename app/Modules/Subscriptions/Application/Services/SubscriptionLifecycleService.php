<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Domain\States\ExpiredState;
use App\Modules\Subscriptions\Domain\States\PastDueState;

class SubscriptionLifecycleService
{
    public function __construct(
        private readonly SubscriptionAuditWriter $auditWriter,
    ) {}

    /** Transition subscription to past_due and set grace window. Must be called inside DB::transaction. */
    public function enterGrace(VendorSubscription $subscription, int $graceDays = 7): void
    {
        $before = ['status' => $subscription->status->getValue()];

        $subscription->status->transitionTo(PastDueState::class);
        $subscription->grace_period_ends_at = ($subscription->current_period_end ?? now())->copy()->addDays($graceDays);
        $subscription->save();

        $this->auditWriter->write(
            subscription: $subscription,
            eventType: SubscriptionEventType::PastDue,
            beforeState: $before,
            afterState: ['status' => 'past_due', 'grace_period_ends_at' => $subscription->grace_period_ends_at?->toISOString()],
            data: ['grace_period_ends_at' => $subscription->grace_period_ends_at?->toISOString()],
        );
    }

    /** Transition to expired while retaining period history. Must be called inside DB::transaction. */
    public function expire(VendorSubscription $subscription, string $reason = 'grace_expired'): void
    {
        $before = ['status' => $subscription->status->getValue()];

        $subscription->status->transitionTo(ExpiredState::class);
        $subscription->ended_reason = $reason;
        $subscription->save();

        $this->auditWriter->write(
            subscription: $subscription,
            eventType: SubscriptionEventType::Expired,
            beforeState: $before,
            afterState: ['status' => 'expired'],
        );
    }
}
