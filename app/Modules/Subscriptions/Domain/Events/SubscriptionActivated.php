<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

// Scalar payload only (actions.md — fix ff6ce5e): listeners re-load the model.
class SubscriptionActivated
{
    use Dispatchable;

    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $billingCycle,
    ) {}
}
