<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Application\Actions\AutoEnrolFreeTierAction;
use App\Modules\Subscriptions\Application\Services\SubscriptionAuditWriter;
use App\Modules\Subscriptions\Application\Services\SubscriptionLifecycleService;
use App\Modules\Subscriptions\Database\Seeders\SubscriptionDevelopmentSeeder;
use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPayment;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Domain\States\ExpiredState;
use App\Modules\Subscriptions\Infrastructure\Repositories\EloquentSubscriptionRepository;
use Illuminate\Support\Facades\DB;

it('enrols a vendor at most once and serializes the lookup in a transaction', function (): void {
    $vendor = VendorProfile::factory()->create();
    SubscriptionPlan::factory()->free()->create();

    $action = app(AutoEnrolFreeTierAction::class);
    $first = $action->execute($vendor->id);
    $second = $action->execute($vendor->id);

    expect($second->is($first))->toBeTrue()
        ->and(VendorSubscription::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(1)
        ->and($first->status->getValue())->toBe(SubscriptionStatus::Active->value);
});

it('does not treat expired periods or overrides as current', function (): void {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->free()->create();

    VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $plan->id,
        'current_period_end' => now()->subSecond(),
    ]);

    $override = VendorSubscription::factory()->withOverride()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $plan->id,
        'current_period_end' => now()->addDay(),
        'override_expires_at' => now()->subSecond(),
    ]);

    expect((new EloquentSubscriptionRepository)->currentForVendor($vendor->id))->toBeNull()
        ->and($override->fresh()->status->getValue())->toBe(SubscriptionStatus::Active->value);
});

it('expires a past due subscription through the allowed state transition and audit writer', function (): void {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->free()->create();
    $subscription = VendorSubscription::factory()->pastDue()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $plan->id,
    ]);
    $audit = Mockery::mock(SubscriptionAuditWriter::class);
    $audit->shouldReceive('write')->once()->withArgs(function (VendorSubscription $record): bool {
        return $record->status->getValue() === SubscriptionStatus::Expired->value;
    });

    DB::transaction(fn () => (new SubscriptionLifecycleService($audit))->expire($subscription));

    expect($subscription->fresh()->status->getValue())->toBe(ExpiredState::$name)
        ->and($subscription->fresh()->ended_reason)->toBe('grace_expired');
});

it('keeps a paid invoice explainable through its captured subscription payment', function (): void {
    $vendor = VendorProfile::factory()->create();
    $subscription = VendorSubscription::factory()->create(['vendor_profile_id' => $vendor->id]);
    $invoice = SubscriptionInvoice::factory()->paid()->create([
        'vendor_subscription_id' => $subscription->id,
        'status' => InvoiceStatus::Paid->value,
        'gateway_ref' => 'subscription-gateway-1',
    ]);

    SubscriptionPayment::query()->create([
        'subscription_invoice_id' => $invoice->id,
        'attempt_no' => 1,
        'mode' => 'vendor_initiated',
        'status' => 'captured',
        'gateway' => 'paymob',
        'gateway_ref' => $invoice->gateway_ref,
    ]);

    expect($invoice->fresh()->payments)->toHaveCount(1)
        ->and($invoice->fresh()->payments->first()->gateway_ref)->toBe($invoice->gateway_ref);
});

it('seeds demo invoices at each new subscription plan price without rewriting existing invoice history', function (): void {
    $silver = SubscriptionPlan::factory()->create([
        'plan_code' => 'silver',
        'monthly_price_minor' => 19_900,
        'monthly_price_currency' => 'USD',
    ]);
    $premium = SubscriptionPlan::factory()->create([
        'plan_code' => 'premium',
        'monthly_price_minor' => 99_900,
    ]);
    $ordinaryVendor = VendorProfile::factory()->create();
    $newSilverVendor = VendorProfile::factory()->create();
    $typeVendor = VendorProfile::factory()->create([
        'user_id' => User::factory()->asVendor()
            ->create(['email' => 'vendor.rental@instaparty.local'])->id,
    ]);
    $existingSubscription = VendorSubscription::factory()->create([
        'vendor_profile_id' => $ordinaryVendor->id,
        'subscription_plan_id' => $silver->id,
        'current_period_start' => now()->startOfMonth(),
        'current_period_end' => now()->startOfMonth()->addMonth(),
    ]);
    $historicalInvoice = SubscriptionInvoice::factory()->paid()->create([
        'vendor_subscription_id' => $existingSubscription->id,
        'period_start' => $existingSubscription->current_period_start,
        'period_end' => $existingSubscription->current_period_end,
        'amount_minor' => 9_900,
        'amount_currency' => 'EGP',
    ]);
    $historicalInvoiceBefore = $historicalInvoice->getRawOriginal();

    app(SubscriptionDevelopmentSeeder::class)->run();

    $silverSubscription = VendorSubscription::query()->where('vendor_profile_id', $newSilverVendor->id)->sole();
    $premiumSubscription = VendorSubscription::query()->where('vendor_profile_id', $typeVendor->id)->sole();
    $silverInvoice = $silverSubscription->invoices()->sole();
    $premiumInvoice = $premiumSubscription->invoices()->sole();

    expect($silverSubscription->subscription_plan_id)->toBe($silver->id)
        ->and($silverInvoice->amount_minor)->toBe(19_900)
        ->and($silverInvoice->amount_currency)->toBe('USD')
        ->and($premiumSubscription->subscription_plan_id)->toBe($premium->id)
        ->and($premiumInvoice->amount_minor)->toBe(99_900)
        ->and($premiumInvoice->payments)->toHaveCount(1)
        ->and(array_intersect_key($historicalInvoice->fresh()->getRawOriginal(), $historicalInvoiceBefore))
        ->toEqual($historicalInvoiceBefore)
        ->and($historicalInvoice->fresh()->payments)->toHaveCount(0);

    app(SubscriptionDevelopmentSeeder::class)->run();

    expect(array_intersect_key($historicalInvoice->fresh()->getRawOriginal(), $historicalInvoiceBefore))
        ->toEqual($historicalInvoiceBefore);
});
