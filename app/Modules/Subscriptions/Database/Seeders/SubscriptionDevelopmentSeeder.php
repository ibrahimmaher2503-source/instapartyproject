<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Database\Seeders;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Models\SubscriptionAuditEntry;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPayment;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Domain\States\ActiveState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $plan = SubscriptionPlan::query()->where('plan_code', 'silver')->first()
            ?? SubscriptionPlan::query()->first();

        $vendors = VendorProfile::query()->limit(2)->get();

        if (! $plan || $vendors->isEmpty()) {
            return;
        }

        foreach ($vendors as $vendor) {
            $subscription = VendorSubscription::query()->updateOrCreate(
                ['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $plan->id, 'ended_at' => null],
                [
                    'public_id' => (string) Str::ulid(),
                    'status' => ActiveState::class,
                    'billing_cycle' => BillingCycle::Monthly,
                    'current_period_start' => now()->startOfMonth(),
                    'current_period_end' => now()->startOfMonth()->addMonth(),
                    'cancel_at_period_end' => false,
                    'is_admin_override' => false,
                    'started_at' => now()->startOfMonth(),
                ],
            );

            $invoice = SubscriptionInvoice::query()->updateOrCreate(
                [
                    'vendor_subscription_id' => $subscription->id,
                    'period_start' => $subscription->current_period_start,
                    'period_end' => $subscription->current_period_end,
                ],
                [
                    'public_id' => (string) Str::ulid(),
                    'idempotency_key' => 'seed:invoice:'.$subscription->id.':'.$subscription->current_period_start->format('Y-m'),
                    'status' => InvoiceStatus::Paid,
                    'billing_cycle' => BillingCycle::Monthly->value,
                    'amount_minor' => 99_00,
                    'amount_currency' => 'EGP',
                    'gateway_ref' => 'seed_pay_'.$subscription->id,
                    'paid_at' => now()->startOfMonth()->addDay(),
                ],
            );

            SubscriptionPayment::query()->firstOrCreate(
                [
                    'subscription_invoice_id' => $invoice->id,
                    'attempt_no' => 1,
                ],
                [
                    'mode' => 'vendor_initiated',
                    'status' => 'captured',
                    'gateway' => 'seed',
                    'gateway_ref' => $invoice->gateway_ref,
                ],
            );

            SubscriptionAuditEntry::query()->firstOrCreate(
                [
                    'vendor_subscription_id' => $subscription->id,
                    'event_type' => SubscriptionEventType::Activated,
                ],
                [
                    'public_id' => (string) Str::ulid(),
                    'vendor_profile_id' => $vendor->id,
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'before_state' => null,
                    'after_state' => ['status' => 'active'],
                    'metadata' => ['source' => 'seeder'],
                    'reason' => 'Initial activation (dev seed)',
                ],
            );
        }

        $this->enrolTypeVendorsOnPremium();
    }

    /**
     * The per-type dev vendors (vendor.rental/sale/digital) otherwise auto-enrol
     * on the free tier (max_active_services = 5) and the catalog seeder fills
     * them past the cap, blocking service-create verification in dev (live
     * audit 2026-06-06). Premium has no active-service cap. Runs after the
     * silver loop and keys on the vendor's single active row so an existing
     * subscription is converted, never duplicated.
     */
    private function enrolTypeVendorsOnPremium(): void
    {
        $premium = SubscriptionPlan::query()->where('plan_code', 'premium')->first();

        if (! $premium) {
            return;
        }

        $typeVendors = VendorProfile::query()
            ->whereHas('user', fn ($q) => $q->whereIn('email', [
                'vendor.rental@instaparty.local',
                'vendor.sale@instaparty.local',
                'vendor.digital@instaparty.local',
            ]))
            ->get();

        foreach ($typeVendors as $vendor) {
            VendorSubscription::query()->updateOrCreate(
                ['vendor_profile_id' => $vendor->id, 'ended_at' => null],
                [
                    'public_id' => (string) Str::ulid(),
                    'subscription_plan_id' => $premium->id,
                    'status' => ActiveState::class,
                    'billing_cycle' => BillingCycle::Monthly,
                    'current_period_start' => now()->startOfMonth(),
                    'current_period_end' => now()->startOfMonth()->addMonth(),
                    'cancel_at_period_end' => false,
                    'is_admin_override' => false,
                    'started_at' => now()->startOfMonth(),
                ],
            );
        }
    }
}
