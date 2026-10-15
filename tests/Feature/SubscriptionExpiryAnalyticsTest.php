<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Application\Actions\ExpireSubscriptionsAction;
use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\Models\SubscriptionAuditEntry;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Filament\Widgets\SubscriptionStatsWidget;
use Brick\Money\Money;

it('advances paid periods through the existing grace window exactly once', function (string $planCode): void {
    $this->freezeTime();
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->create(['plan_code' => $planCode]);
    $base = ['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $plan->id];
    $boundary = VendorSubscription::factory()->create($base + ['current_period_end' => now()]);
    $old = VendorSubscription::factory()->create($base + ['current_period_end' => now()->subDays(7)]);
    $permanent = VendorSubscription::factory()->create($base + ['current_period_end' => null]);
    $future = VendorSubscription::factory()->create($base + ['current_period_end' => now()->addSecond()]);
    $pastDue = VendorSubscription::factory()->pastDue()->create($base + ['grace_period_ends_at' => now()]);

    $action = app(ExpireSubscriptionsAction::class);
    $action->execute();
    $auditCount = SubscriptionAuditEntry::query()->count();
    $action->execute();

    expect($boundary->fresh()->status->getValue())->toBe('past_due')
        ->and($boundary->fresh()->grace_period_ends_at->timestamp)->toBe(now()->addDays(7)->timestamp)
        ->and($old->fresh()->status->getValue())->toBe('expired')
        ->and($pastDue->fresh()->status->getValue())->toBe('expired')
        ->and($permanent->fresh()->status->getValue())->toBe('active')
        ->and($future->fresh()->status->getValue())->toBe('active')
        ->and(SubscriptionAuditEntry::query()->count())->toBe($auditCount);
})->with(['silver', 'premium']);

it('leaves free subscriptions intact and closes cancellations and expired overrides', function (): void {
    $this->freezeTime();
    $vendor = VendorProfile::factory()->create();
    $free = SubscriptionPlan::factory()->free()->create();
    $paid = SubscriptionPlan::factory()->create(['plan_code' => 'silver']);
    $permanent = VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $free->id,
        'billing_cycle' => BillingCycle::None->value, 'current_period_end' => null,
    ]);
    $base = ['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $paid->id];
    $cancelled = VendorSubscription::factory()->create($base + ['cancel_at_period_end' => true, 'current_period_end' => now()]);
    $override = VendorSubscription::factory()->withOverride()->create($base + ['override_expires_at' => now(), 'current_period_end' => null]);

    app(ExpireSubscriptionsAction::class)->execute();

    expect($permanent->fresh()->status->getValue())->toBe('active')
        ->and($cancelled->fresh()->status->getValue())->toBe('expired')
        ->and($cancelled->fresh()->ended_reason)->toBe('cancel_at_period_end')
        ->and($override->fresh()->status->getValue())->toBe('expired');
});

it('counts only effective active rows and never adds foreign currency or overrides to EGP MRR', function (): void {
    $this->freezeTime();
    $vendor = VendorProfile::factory()->create();
    $silver = SubscriptionPlan::factory()->create(['plan_code' => 'silver', 'monthly_price_minor' => 10000, 'yearly_price_minor' => 900000, 'yearly_price_currency' => 'USD']);
    $foreign = SubscriptionPlan::factory()->create(['plan_code' => 'premium', 'monthly_price_minor' => 90000, 'monthly_price_currency' => 'USD', 'yearly_price_minor' => 120018, 'yearly_price_currency' => 'EGP']);
    $free = SubscriptionPlan::factory()->free()->create();
    $base = ['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $silver->id];
    VendorSubscription::factory()->create($base + ['current_period_end' => now()->addDays(30)]);
    VendorSubscription::factory()->create($base + ['current_period_end' => now()]);
    VendorSubscription::factory()->create($base + ['current_period_end' => now()->subDay()]);
    VendorSubscription::factory()->withOverride()->create($base + ['override_expires_at' => now()]);
    VendorSubscription::factory()->withOverride()->create($base + ['current_period_end' => null]);
    VendorSubscription::factory()->create(['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $foreign->id, 'current_period_end' => now()->addDays(31)]);
    VendorSubscription::factory()->create(['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $foreign->id, 'billing_cycle' => BillingCycle::Yearly->value, 'current_period_end' => now()->addYear()]);
    VendorSubscription::factory()->create($base + ['billing_cycle' => BillingCycle::Yearly->value, 'current_period_end' => now()->addYear()]);
    VendorSubscription::factory()->create(['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $free->id, 'billing_cycle' => BillingCycle::None->value, 'current_period_end' => null]);

    $widget = new class extends SubscriptionStatsWidget
    {
        public function stats(): array
        {
            return $this->getStats();
        }
    };
    $stats = $widget->stats();

    expect($stats[0]->getValue())->toBe(6)
        ->and($stats[1]->getValue())->toBe(Money::ofMinor(20002, 'EGP')->formatTo(app()->getLocale()))
        ->and($stats[2]->getValue())->toBe(1)
        ->and($stats[3]->getValue())->toBe(1);
});
