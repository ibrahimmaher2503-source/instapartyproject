<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Actions;

use App\Modules\Subscriptions\Application\Services\SubscriptionLifecycleService;
use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\Enums\PlanCode;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExpireSubscriptionsAction
{
    public function __construct(private readonly SubscriptionLifecycleService $lifecycle) {}

    public function execute(): void
    {
        $at = now();
        $due = VendorSubscription::query()->where(function (Builder $query) use ($at): void {
            $query->where(function (Builder $query) use ($at): void {
                $query->where('status', SubscriptionStatus::Active->value)
                    ->where(function (Builder $query) use ($at): void {
                        $query->where('current_period_end', '<=', $at)
                            ->orWhere(function (Builder $query) use ($at): void {
                                $query->where('is_admin_override', true)
                                    ->where('override_expires_at', '<=', $at);
                            });
                    });
            })->orWhere(function (Builder $query) use ($at): void {
                $query->inGrace($at);
            });
        });

        (clone $due)->select('id')->chunkById(100, function ($rows) use ($due, $at): void {
            DB::transaction(function () use ($rows, $due, $at): void {
                $subscriptions = (clone $due)->whereKey($rows->modelKeys())->lockForUpdate()->with('plan')->get();

                foreach ($subscriptions as $subscription) {
                    if ($subscription->status->getValue() === SubscriptionStatus::PastDue->value) {
                        $this->lifecycle->expire($subscription);

                        continue;
                    }

                    if ($subscription->is_admin_override) {
                        if ($subscription->override_expires_at?->lte($at) || $subscription->current_period_end?->lte($at)) {
                            $this->lifecycle->expire($subscription, 'override_expired');
                        }

                        continue;
                    }

                    if ($subscription->billing_cycle === BillingCycle::None || $subscription->plan?->plan_code === PlanCode::Free) {
                        continue;
                    }

                    if ($subscription->cancel_at_period_end) {
                        $this->lifecycle->expire($subscription, 'cancel_at_period_end');

                        continue;
                    }

                    $this->lifecycle->enterGrace($subscription);
                    if ($subscription->grace_period_ends_at->lte($at)) {
                        $this->lifecycle->expire($subscription);
                    }
                }
            });
        });
    }
}
